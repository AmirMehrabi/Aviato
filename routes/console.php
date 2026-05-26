<?php

use App\Services\InvoiceService;
use App\Services\StaleVirtualMachineCleanupService;
use App\Services\UsageBillingService;
use App\Services\VmBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('billing:charge-usage', function (UsageBillingService $billing) {
    $transactions = $billing->chargeAllDueUsage();

    $this->info(sprintf('Created %d usage charge transaction(s).', $transactions->count()));
})->purpose('Charge accrued PAYG usage to customer wallets');

Artisan::command('billing:generate-monthly-invoices', function (InvoiceService $invoices) {
    $generated = $invoices->generateMonthlyInvoices();

    $this->info(sprintf('Generated %d monthly invoice(s).', $generated->count()));
})->purpose('Generate monthly customer usage invoices');

Artisan::command('virtual-machines:cleanup-stale {--server= : Limit scan to one Proxmox server ID} {--dry-run : Show stale records without deleting them} {--yes : Delete without an interactive confirmation}', function (StaleVirtualMachineCleanupService $cleanup) {
    $serverId = $this->option('server') ? (int) $this->option('server') : null;
    $reports = $cleanup->scanAll($serverId);
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
Schedule::command('billing:generate-monthly-invoices')->monthlyOn(1, '00:15');
Schedule::command('backup:run-due')->everyFifteenMinutes();
Schedule::command('backup:sync')->hourlyAt(10);
