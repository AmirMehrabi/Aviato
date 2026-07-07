<?php

use App\Jobs\DeleteVirtualMachineJob;
use App\Jobs\ReconcilePendingVirtualMachine;
use App\Models\HetznerAccount;
use App\Models\VirtualMachine;
use App\Services\HetznerCatalogSyncService;
use App\Services\InvoiceService;
use App\Services\ProxmoxService;
use App\Services\StaleVirtualMachineCleanupService;
use App\Services\UsageBillingService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VmBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('billing:charge-usage', function (UsageBillingService $billing) {
    $accruals = $billing->accrueAllDueUsage();

    $this->info(sprintf(
        'Updated %d usage accrual record(s), totaling %d IRR.',
        $accruals->count(),
        $accruals->sum('amount'),
    ));
})->purpose('Accrue hourly PAYG usage without creating wallet transactions');

Artisan::command('billing:settle-usage {--date= : Service date to settle in YYYY-MM-DD format}', function (UsageBillingService $billing) {
    $date = $this->option('date') ?: now()->subDay()->toDateString();
    $settlements = $billing->settleDate($date);

    $this->info(sprintf(
        'Settled %d customer/project usage group(s) for %s, totaling %d IRR.',
        $settlements->count(),
        $date,
        $settlements->sum('amount'),
    ));
})->purpose('Create daily aggregate wallet charges from hourly usage accruals');

Artisan::command('billing:generate-monthly-invoices', function (InvoiceService $invoices) {
    $generated = $invoices->generateMonthlyInvoices();

    $this->info(sprintf('Generated %d monthly invoice(s).', $generated->count()));
})->purpose('Generate monthly customer usage invoices');

Artisan::command('hetzner:sync-catalog {--account= : Limit sync to one Hetzner account ID}', function (HetznerCatalogSyncService $sync) {
    $accountId = $this->option('account');

    if ($accountId) {
        $account = HetznerAccount::query()->findOrFail((int) $accountId);
        $sync->sync($account);
        $this->info('Synced Hetzner account #'.$account->id.'.');

        return Command::SUCCESS;
    }

    $count = $sync->syncAll();
    $this->info(sprintf('Synced %d Hetzner account(s).', $count));

    return Command::SUCCESS;
})->purpose('Sync Hetzner locations, images, server types, and price snapshots');

Artisan::command('virtual-machines:inspect-delete {virtualMachine : Local VM id, UUID, name, or Proxmox VMID} {--server= : Required when identifying by VMID if it is ambiguous} {--sync : Run the delete job immediately instead of only queueing it} {--yes : Skip the confirmation prompt}', function (ProxmoxService $proxmox, VirtualMachineDeletionService $deletions) {
    $identifier = (string) $this->argument('virtualMachine');
    $serverId = $this->option('server') ? (int) $this->option('server') : null;

    $query = VirtualMachine::query()
        ->with(['customer', 'proxmoxServer', 'bundle', 'reservedIpAddress']);

    $matches = (clone $query)
        ->where(function ($query) use ($identifier): void {
            $query->where('uuid', $identifier)
                ->orWhere('name', $identifier)
                ->when(ctype_digit($identifier), function ($query) use ($identifier): void {
                    $query->orWhere('id', (int) $identifier)
                        ->orWhere('vmid', (int) $identifier);
                });
        })
        ->when($serverId !== null, fn ($query) => $query->where('proxmox_server_id', $serverId))
        ->get();

    if ($matches->isEmpty()) {
        $this->error(sprintf('No virtual machine matched "%s".', $identifier));

        return Command::FAILURE;
    }

    if ($matches->count() > 1) {
        $this->warn(sprintf('"%s" matched multiple local virtual machines.', $identifier));
        $this->table(
            ['ID', 'UUID', 'VMID', 'Name', 'Proxmox', 'Customer', 'Status'],
            $matches->map(fn (VirtualMachine $vm): array => [
                $vm->id,
                $vm->uuid,
                $vm->vmid ?? '—',
                $vm->name,
                $vm->proxmoxServer?->name ?? '#'.$vm->proxmox_server_id,
                $vm->customer?->name ?? '—',
                $vm->status,
            ])->all(),
        );
        $this->line('Pass a UUID/local ID, or add --server=<id> when using a Proxmox VMID.');

        return Command::FAILURE;
    }

    /** @var VirtualMachine $vm */
    $vm = $matches->first();

    $this->info('Application record');
    $this->table(
        ['Field', 'Value'],
        [
            ['ID', $vm->id],
            ['UUID', $vm->uuid],
            ['Name', $vm->name],
            ['Hostname', $vm->hostname ?? '—'],
            ['Customer', $vm->customer?->name ?? '—'],
            ['Proxmox server', $vm->proxmoxServer?->name.' (#'.$vm->proxmox_server_id.')'],
            ['Node', $vm->node ?? '—'],
            ['VMID', $vm->vmid ?? '—'],
            ['IP', $vm->ip_address ?? $vm->reservedIpAddress?->address ?? '—'],
            ['Status', $vm->status],
            ['Provisioning', $vm->provisioning_status],
            ['CPU cores', $vm->cpu_cores],
            ['RAM GB', $vm->ram_gb],
            ['Disk GB', $vm->disk_gb],
            ['Delete requested at', $vm->delete_requested_at?->toDateTimeString() ?? '—'],
            ['Delete failed at', $vm->delete_failed_at?->toDateTimeString() ?? '—'],
            ['Delete error', $vm->delete_error ?? '—'],
        ],
    );

    if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
        $this->warn('This VM does not have enough Proxmox connection data. Confirmation will finalize the local delete only.');
    } else {
        $this->info('Proxmox record');

        try {
            $status = $proxmox->vmStatus($vm->proxmoxServer, $vm->node, (int) $vm->vmid);
            $config = $proxmox->vmConfigOrNull($vm->proxmoxServer, $vm->node, (int) $vm->vmid);

            if ($status === null && $config === null) {
                $this->warn('Proxmox did not return a VM for this node and VMID.');
            } else {
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Node', $vm->node],
                        ['VMID', $vm->vmid],
                        ['Status', $status['status'] ?? '—'],
                        ['Remote name', $config['name'] ?? '—'],
                        ['CPUs', $config['cores'] ?? $config['sockets'] ?? '—'],
                        ['Memory MB', $config['memory'] ?? '—'],
                        ['Boot order', $config['boot'] ?? '—'],
                        ['Machine', $config['machine'] ?? '—'],
                    ],
                );

                if (($config['name'] ?? null) !== null && ($config['name'] ?? null) !== $vm->name) {
                    $this->warn(sprintf(
                        'Remote name "%s" does not match local name "%s"; the normal delete job will not destroy a mismatched remote VM.',
                        $config['name'],
                        $vm->name,
                    ));
                }
            }
        } catch (Throwable $exception) {
            $this->error('Could not query Proxmox: '.$exception->getMessage());

            return Command::FAILURE;
        }
    }

    if (! $this->option('yes') && ! $this->confirm(sprintf(
        'Delete VM #%d (%s, VMID %s) using the normal remote-first delete lifecycle?',
        $vm->id,
        $vm->name,
        $vm->vmid ?? 'none',
    ))) {
        $this->info('Delete cancelled.');

        return Command::SUCCESS;
    }

    $result = $deletions->requestDelete($vm, 'artisan');

    if ($result['finalized']) {
        $this->info(sprintf('VM #%d was finalized locally as deleted.', $result['vm']->id));

        return Command::SUCCESS;
    }

    if (! $result['queued']) {
        $this->info(sprintf('No delete job was queued. Status: %s.', $result['status']));

        return Command::SUCCESS;
    }

    if ($this->option('sync')) {
        $this->info('Running delete job now...');
        (new DeleteVirtualMachineJob($result['vm']->id))->handle($proxmox, $deletions);
        $this->info(sprintf('Delete job completed. Current local status: %s.', $result['vm']->refresh()->status));

        return Command::SUCCESS;
    }

    $this->info(sprintf('Delete job queued for VM #%d. Make sure a queue worker is running on the default queue.', $result['vm']->id));

    return Command::SUCCESS;
})->purpose('Inspect a local and Proxmox VM record, then delete it after confirmation');

Artisan::command('virtual-machines:cleanup-stale {--server= : Limit scan to one Proxmox server ID} {--include-deleting : Include records already locked in deleting status} {--dry-run : Show stale records without deleting them} {--yes : Delete without an interactive confirmation}', function (StaleVirtualMachineCleanupService $cleanup) {
    $serverId = $this->option('server') ? (int) $this->option('server') : null;
    $reports = $cleanup->scanAll($serverId, (bool) $this->option('include-deleting'));
    $stale = $reports->flatMap(fn (array $report) => $report['stale']);
    $errors = $reports->filter(fn (array $report): bool => filled($report['error']));

    foreach ($errors as $report) {
        $this->warn(sprintf(
            'Could not scan %s (#%d): %s',
            $report['server']->name,
            $report['server']->id,
            $report['error'],
        ));
    }

    if ($stale->isEmpty()) {
        $this->info('No stale virtual machine records were found.');

        return Command::SUCCESS;
    }

    $this->table(
        ['ID', 'Proxmox', 'VMID', 'Name', 'Customer', 'IP', 'Status', 'Last billed'],
        $stale->map(fn ($vm): array => [
            $vm->id,
            $vm->proxmoxServer?->name ?? '#'.$vm->proxmox_server_id,
            $vm->vmid,
            $vm->name,
            $vm->customer?->name ?? '—',
            $vm->ip_address ?? '—',
            $vm->status,
            $vm->last_billed_at?->toDateTimeString() ?? 'never',
        ])->all(),
    );

    if ($this->option('dry-run')) {
        $this->info(sprintf('%d stale record(s) found. Dry run only; nothing was deleted.', $stale->count()));

        return Command::SUCCESS;
    }

    if (! $this->option('yes') && ! $this->confirm(sprintf(
        'Delete %d stale local VM record(s), release their IPs, and charge accrued usage where applicable?',
        $stale->count(),
    ))) {
        $this->info('Cleanup cancelled.');

        return Command::SUCCESS;
    }

    $deleted = 0;

    foreach ($stale as $vm) {
        try {
            $result = $cleanup->cleanup($vm, 'artisan');
            $deleted++;
            $this->line(sprintf(
                'Deleted local VM #%d (old VMID %s, released IP %s, billing transaction %s).',
                $result['vm']->id,
                $result['deleted_vmid'] ?? '—',
                $result['released_ip'] ?? 'none',
                $result['wallet_transaction']?->id ?? 'none',
            ));
        } catch (Throwable $exception) {
            $this->error(sprintf('VM #%d was not deleted: %s', $vm->id, $exception->getMessage()));
        }
    }

    $this->info(sprintf('Cleanup finished. Deleted %d of %d stale record(s).', $deleted, $stale->count()));

    return Command::SUCCESS;
})->purpose('Find local VM records missing from Proxmox and safely mark them deleted');

Artisan::command('virtual-machines:reconcile-pending {--minutes=10 : Minimum pending age before checking Proxmox} {--limit=50 : Maximum records to queue}', function () {
    $minutes = max(1, (int) $this->option('minutes'));
    $limit = max(1, (int) $this->option('limit'));

    $vms = VirtualMachine::query()
        ->where(function ($query): void {
            $query->where('provider', VirtualMachine::PROVIDER_PROXMOX)
                ->orWhereNull('provider');
        })
        ->where('provisioning_status', VirtualMachine::PROVISION_PENDING)
        ->where('updated_at', '<=', now()->subMinutes($minutes))
        ->orderBy('updated_at')
        ->limit($limit)
        ->get(['id', 'name', 'vmid', 'updated_at']);

    foreach ($vms as $vm) {
        ReconcilePendingVirtualMachine::dispatch($vm->id)->onQueue(ReconcilePendingVirtualMachine::QUEUE);
    }

    $this->info(sprintf('Queued %d pending VM reconciliation job(s).', $vms->count()));

    return Command::SUCCESS;
})->purpose('Queue throttled Proxmox checks for old pending VM provisioning records');

Artisan::command('backup:run-due', function (VmBackupService $backups) {
    $queued = $backups->dispatchDuePolicies();

    $this->info(sprintf('Queued %d scheduled backup(s).', $queued->count()));
})->purpose('Queue due VM backup policies');

Artisan::command('backup:sync', function (VmBackupService $backups) {
    $synced = $backups->syncBackupsFromProxmox();

    $this->info(sprintf('Synced %d backup file(s).', $synced));
})->purpose('Sync Proxmox backup files into the panel');

Artisan::command('inspire', function () {
    $this->comment('Keep shipping.');
})->purpose('Display an inspiring quote');

Schedule::command('billing:charge-usage')->hourly();
Schedule::command('billing:settle-usage')->dailyAt('00:05');
Schedule::command('hetzner:sync-catalog')->hourlyAt(5);
Schedule::command('billing:generate-monthly-invoices')->monthlyOn(1, '00:15');
Schedule::command('backup:run-due')->everyFifteenMinutes();
Schedule::command('backup:sync')->hourlyAt(10);
Schedule::command('virtual-machines:reconcile-pending')->everyTenMinutes();
