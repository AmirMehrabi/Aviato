<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\Invoice;
use App\Models\IpAddress;
use App\Models\Payment;
use App\Models\ProxmoxServer;
use App\Models\Ticket;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmUpgradeOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function __invoke(): View
    {
        $vmBase = VirtualMachine::query()->notDeleted();
        $totalVms = (clone $vmBase)->count();
        $runningVms = (clone $vmBase)->where('status', VirtualMachine::STATUS_RUNNING)->count();
        $pendingProvisioning = (clone $vmBase)->where('provisioning_status', VirtualMachine::PROVISION_PENDING)->count();
        $failedProvisioning = (clone $vmBase)->where('provisioning_status', VirtualMachine::PROVISION_FAILED)->count();
        $deletingVms = (clone $vmBase)->where('status', VirtualMachine::STATUS_DELETING)->count();
        $staleDeleteAttempts = (clone $vmBase)
            ->where('status', VirtualMachine::STATUS_DELETING)
            ->where(function ($query): void {
                $query->whereNotNull('delete_failed_at')
                    ->orWhere('delete_started_at', '<=', now()->subMinutes(15))
                    ->orWhere('delete_requested_at', '<=', now()->subMinutes(15));
            })
            ->count();

        $proxmoxTotal = ProxmoxServer::query()->count();
        $proxmoxOnline = ProxmoxServer::query()->where('connection_status', ProxmoxServer::CONNECTION_ONLINE)->count();
        $proxmoxOffline = ProxmoxServer::query()->where('connection_status', ProxmoxServer::CONNECTION_OFFLINE)->count();
        $proxmoxPendingSync = ProxmoxServer::query()->where('sync_status', ProxmoxServer::SYNC_PENDING)->count();
        $negativeWallets = Wallet::query()->where('balance', '<', 0)->count();
        $lockedWallets = Wallet::query()->where('is_locked', true)->count();
        $pendingPayments = Payment::query()->where('status', Payment::STATUS_PENDING)->count();
        $failedPaymentsToday = Payment::query()->where('status', Payment::STATUS_FAILED)->where('created_at', '>=', now()->startOfDay())->count();
        $failedBackups = VmBackup::query()->where('status', VmBackup::STATUS_FAILED)->count();
        $pendingUpgrades = VmUpgradeOrder::query()
            ->whereIn('status', [VmUpgradeOrder::STATUS_PENDING, VmUpgradeOrder::STATUS_APPLYING])
            ->count();
        $newContacts = ContactSubmission::query()->where('status', ContactSubmission::STATUS_NEW)->count();

        $todayRevenue = (int) WalletTransaction::query()
            ->where('amount', '>', 0)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');
        $monthRevenue = (int) WalletTransaction::query()
            ->where('amount', '>', 0)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
        $monthInvoiceSum = (int) Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount');
        $monthInvoiceCount = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $negativeWalletTotal = abs((int) Wallet::query()->where('balance', '<', 0)->sum('balance'));

        $ticketsOpen = Ticket::query()->where('status', Ticket::STATUS_OPEN)->count();
        $ticketsPending = Ticket::query()->where('status', Ticket::STATUS_PENDING)->count();
        $ticketByPriority = [
            'urgent' => Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING])->where('priority', Ticket::PRIORITY_URGENT)->count(),
            'high' => Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING])->where('priority', Ticket::PRIORITY_HIGH)->count(),
            'normal' => Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING])->where('priority', Ticket::PRIORITY_NORMAL)->count(),
            'low' => Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING])->where('priority', Ticket::PRIORITY_LOW)->count(),
        ];
        $recentTickets = Ticket::query()
            ->with('customer')
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Ticket $t) => [
                'number' => $t->number,
                'subject' => $t->subject,
                'customer' => $t->customer?->name ?? '—',
                'priority' => $t->priority,
                'priority_label' => Ticket::priorities()[$t->priority] ?? $t->priority,
                'status' => $t->status,
                'status_label' => Ticket::statuses()[$t->status] ?? $t->status,
                'time' => $t->last_activity_at?->diffForHumans() ?? $t->created_at->diffForHumans(),
            ]);

        $thirtyDaysAgo = now()->subDays(29)->startOfDay();
        $revenueChartLabels = [];
        $revenueChartData = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $thirtyDaysAgo->copy()->addDays($i);
            $revenueChartLabels[] = $day->format('M/d');
            $revenueChartData[] = (int) WalletTransaction::query()
                ->where('amount', '>', 0)
                ->whereDate('created_at', $day)
                ->sum('amount');
        }

        $attentionItems = $this->attentionItems();
        $resourceTotals = $this->resourceTotals();
        $capacityRows = $this->capacityRows($resourceTotals);
        $recentActivity = $this->recentActivity();

        $readyScore = $this->readinessScore($proxmoxTotal, $proxmoxOnline, $proxmoxOffline, $failedProvisioning, $failedBackups, $staleDeleteAttempts);

        return view('admin.dashboard', [
            'statusStrip' => [
                [
                    'label' => 'زیرساخت',
                    'value' => "{$proxmoxOnline}/{$proxmoxTotal}",
                    'sub' => $proxmoxOffline > 0 ? $proxmoxOffline.' آفلاین' : 'همه آنلاین',
                    'tone' => $proxmoxOffline > 0 ? 'red' : ($proxmoxPendingSync > 0 ? 'amber' : 'green'),
                    'url' => route('admin.proxmox-servers.index'),
                ],
                [
                    'label' => 'VM Fleet',
                    'value' => "{$runningVms}/{$totalVms}",
                    'sub' => $failedProvisioning > 0 ? $failedProvisioning.' ساخت ناموفق' : ($pendingProvisioning > 0 ? $pendingProvisioning.' در صف' : 'همه فعال'),
                    'tone' => $failedProvisioning > 0 ? 'red' : ($pendingProvisioning > 0 ? 'amber' : 'green'),
                    'url' => route('admin.virtual-machines.index'),
                ],
                [
                    'label' => 'درآمد امروز',
                    'value' => $this->wallets->format($todayRevenue),
                    'sub' => $this->wallets->format($monthRevenue).' ماهانه',
                    'tone' => 'green',
                    'url' => route('admin.customers.index'),
                ],
                [
                    'label' => 'تیکت‌ها',
                    'value' => $ticketsOpen + $ticketsPending,
                    'sub' => $ticketByPriority['urgent'] > 0 ? $ticketByPriority['urgent'].' فوری' : 'بدون فوری',
                    'tone' => $ticketByPriority['urgent'] > 0 ? 'red' : (($ticketsOpen + $ticketsPending) > 0 ? 'amber' : 'green'),
                    'url' => route('admin.tickets.index'),
                ],
                [
                    'label' => 'هشدارها',
                    'value' => $attentionItems->count(),
                    'sub' => $attentionItems->where('tone', 'red')->count().' بحرانی',
                    'tone' => $attentionItems->where('tone', 'red')->count() > 0 ? 'red' : ($attentionItems->count() > 0 ? 'amber' : 'green'),
                    'url' => '#operations-section',
                ],
            ],
            'revenueChart' => [
                'labels' => $revenueChartLabels,
                'data' => $revenueChartData,
            ],
            'serverHealth' => $capacityRows,
            'criticalAlerts' => $attentionItems->where('tone', '!=', 'blue')->take(6)->values(),
            'ticketStats' => [
                'open' => $ticketsOpen,
                'pending' => $ticketsPending,
                'by_priority' => $ticketByPriority,
            ],
            'recentTickets' => $recentTickets,
            'financial' => [
                'today_revenue' => $todayRevenue,
                'month_revenue' => $monthRevenue,
                'month_invoices' => $monthInvoiceCount,
                'month_invoice_sum' => $monthInvoiceSum,
                'negative_wallets' => $negativeWallets,
                'negative_total' => $this->wallets->format($negativeWalletTotal),
                'pending_payments' => $pendingPayments,
                'locked_wallets' => $lockedWallets,
            ],
            'recentActivity' => $recentActivity,
            'health' => [
                'proxmox_total' => $proxmoxTotal,
                'proxmox_online' => $proxmoxOnline,
                'ready_score' => $readyScore,
            ],
            'wallets' => $this->wallets,
        ]);
    }

    private function attentionItems(): Collection
    {
        $items = collect();

        VirtualMachine::query()
            ->notDeleted()
            ->with(['customer', 'proxmoxServer'])
            ->where('provisioning_status', VirtualMachine::PROVISION_FAILED)
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VirtualMachine $vm) => $items->push([
                'priority' => 100,
                'tone' => 'red',
                'label' => 'Provisioning failed',
                'title' => $vm->name,
                'meta' => ($vm->customer?->name ?: 'بدون مشتری').' - '.($vm->proxmoxServer?->name ?: 'بدون Proxmox'),
                'url' => route('admin.virtual-machines.show', $vm),
                'action' => 'بررسی و Retry',
            ]));

        VirtualMachine::query()
            ->notDeleted()
            ->with(['customer', 'proxmoxServer'])
            ->where('status', VirtualMachine::STATUS_DELETING)
            ->where(function ($query): void {
                $query->whereNotNull('delete_failed_at')
                    ->orWhere('delete_started_at', '<=', now()->subMinutes(15))
                    ->orWhere('delete_requested_at', '<=', now()->subMinutes(15));
            })
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VirtualMachine $vm) => $items->push([
                'priority' => 95,
                'tone' => 'red',
                'label' => 'Delete attention',
                'title' => $vm->name,
                'meta' => $vm->delete_error ?: 'حذف بیش از ۱۵ دقیقه درگیر است',
                'url' => route('admin.virtual-machines.show', $vm),
                'action' => 'مشاهده حذف',
            ]));

        ProxmoxServer::query()
            ->where(function ($query): void {
                $query->where('connection_status', ProxmoxServer::CONNECTION_OFFLINE)
                    ->orWhere('sync_status', ProxmoxServer::SYNC_FAILED)
                    ->orWhere('sync_status', ProxmoxServer::SYNC_PENDING);
            })
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (ProxmoxServer $server) => $items->push([
                'priority' => $server->connection_status === ProxmoxServer::CONNECTION_OFFLINE ? 90 : 70,
                'tone' => $server->connection_status === ProxmoxServer::CONNECTION_OFFLINE ? 'red' : 'amber',
                'label' => 'Proxmox',
                'title' => $server->name,
                'meta' => $server->connection_status.' / '.$server->sync_status,
                'url' => route('admin.proxmox-servers.show', $server),
                'action' => 'Sync',
            ]));

        VmBackup::query()
            ->with('virtualMachine.customer')
            ->where('status', VmBackup::STATUS_FAILED)
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VmBackup $backup) => $items->push([
                'priority' => 80,
                'tone' => 'amber',
                'label' => 'Backup failed',
                'title' => $backup->virtualMachine?->name ?: 'Backup #'.$backup->id,
                'meta' => $backup->error ?: ($backup->storage ?: 'بدون storage'),
                'url' => $backup->virtualMachine ? route('admin.virtual-machines.show', $backup->virtualMachine) : route('admin.virtual-machines.index'),
                'action' => 'مشاهده VM',
            ]));

        VmUpgradeOrder::query()
            ->with(['virtualMachine.customer'])
            ->whereIn('status', [VmUpgradeOrder::STATUS_PENDING, VmUpgradeOrder::STATUS_APPLYING, VmUpgradeOrder::STATUS_FAILED])
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VmUpgradeOrder $order) => $items->push([
                'priority' => $order->status === VmUpgradeOrder::STATUS_FAILED ? 85 : 55,
                'tone' => $order->status === VmUpgradeOrder::STATUS_FAILED ? 'red' : 'blue',
                'label' => 'Upgrade '.$order->status,
                'title' => $order->virtualMachine?->name ?: 'Upgrade #'.$order->id,
                'meta' => $order->failure_reason ?: $this->wallets->format((int) $order->estimated_monthly_delta).' تغییر ماهانه',
                'url' => $order->virtualMachine ? route('admin.virtual-machines.show', $order->virtualMachine) : route('admin.virtual-machines.index'),
                'action' => 'پیگیری',
            ]));

        Wallet::query()
            ->with('customer')
            ->where(function ($query): void {
                $query->where('balance', '<', 0)->orWhere('is_locked', true);
            })
            ->orderBy('balance')
            ->limit(4)
            ->get()
            ->each(fn (Wallet $wallet) => $items->push([
                'priority' => $wallet->balance < 0 ? 75 : 45,
                'tone' => $wallet->balance < 0 ? 'red' : 'amber',
                'label' => $wallet->is_locked ? 'Wallet locked' : 'Wallet negative',
                'title' => $wallet->customer?->name ?: 'Customer #'.$wallet->customer_id,
                'meta' => $this->wallets->format((int) $wallet->balance),
                'url' => $wallet->customer ? route('admin.customers.show', $wallet->customer) : route('admin.customers.index'),
                'action' => 'مالی مشتری',
            ]));

        return $items->sortByDesc('priority')->take(10)->values();
    }

    private function resourceTotals(): array
    {
        $row = VirtualMachine::query()
            ->notDeleted()
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(cpu_cores), 0) as cpu, COALESCE(SUM(ram_gb), 0) as ram, COALESCE(SUM(disk_gb), 0) as disk, COALESCE(SUM(ip_count), 0) as ips')
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'cpu' => (int) ($row?->cpu ?? 0),
            'ram' => (int) ($row?->ram ?? 0),
            'disk' => (int) ($row?->disk ?? 0),
            'ips' => (int) ($row?->ips ?? 0),
            'available_ips' => IpAddress::query()->where('status', IpAddress::STATUS_AVAILABLE)->count(),
            'assigned_ips' => IpAddress::query()->where('status', IpAddress::STATUS_ASSIGNED)->count(),
        ];
    }

    private function capacityRows(array $resourceTotals): array
    {
        $serverRows = ProxmoxServer::query()
            ->withCount(['virtualMachines as live_vms_count' => fn ($query) => $query->notDeleted()])
            ->orderBy('datacenter')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (ProxmoxServer $server): array => [
                'name' => $server->name,
                'value' => $server->connection_status === ProxmoxServer::CONNECTION_ONLINE ? 100 : ($server->connection_status === ProxmoxServer::CONNECTION_UNKNOWN ? 45 : 12),
                'detail' => ($server->datacenter ?: 'بدون دیتاسنتر').' - '.$server->live_vms_count.' VM',
                'color' => $server->connection_status === ProxmoxServer::CONNECTION_ONLINE ? 'bg-[#0069FF]' : ($server->connection_status === ProxmoxServer::CONNECTION_UNKNOWN ? 'bg-amber-500' : 'bg-red-500'),
            ])
            ->all();

        if ($serverRows !== []) {
            return $serverRows;
        }

        return [
            [
                'name' => 'IP Pool',
                'value' => $this->percentage($resourceTotals['assigned_ips'], max($resourceTotals['assigned_ips'] + $resourceTotals['available_ips'], 1)),
                'detail' => $resourceTotals['available_ips'].' IP آزاد',
                'color' => 'bg-[#0069FF]',
            ],
        ];
    }

    private function recentActivity(): Collection
    {
        $transactions = WalletTransaction::query()
            ->with('customer')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (WalletTransaction $transaction): array => [
                'time' => $transaction->created_at,
                'tone' => $transaction->amount >= 0 ? 'blue' : 'red',
                'title' => $transaction->description ?: 'Wallet transaction',
                'meta' => ($transaction->customer?->name ?: 'بدون مشتری').' - '.$this->wallets->format((int) $transaction->amount),
                'url' => $transaction->customer ? route('admin.customers.show', $transaction->customer) : route('admin.customers.index'),
            ]);

        $payments = Payment::query()
            ->with('customer')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Payment $payment): array => [
                'time' => $payment->created_at,
                'tone' => $payment->status === Payment::STATUS_SUCCESSFUL ? 'blue' : ($payment->status === Payment::STATUS_FAILED ? 'red' : 'amber'),
                'title' => 'Payment '.$payment->status,
                'meta' => ($payment->customer?->name ?: 'بدون مشتری').' - '.$this->wallets->format((int) $payment->amount),
                'url' => $payment->customer ? route('admin.customers.show', $payment->customer) : route('admin.customers.index'),
            ]);

        $vms = VirtualMachine::query()
            ->notDeleted()
            ->with('customer')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (VirtualMachine $vm): array => [
                'time' => $vm->created_at,
                'tone' => $vm->provisioning_status === VirtualMachine::PROVISION_FAILED ? 'red' : 'blue',
                'title' => 'VM '.$vm->name,
                'meta' => ($vm->customer?->name ?: 'بدون مشتری').' - '.$vm->status.' / '.$vm->provisioning_status,
                'url' => route('admin.virtual-machines.show', $vm),
            ]);

        $contacts = ContactSubmission::query()
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (ContactSubmission $contact): array => [
                'time' => $contact->created_at,
                'tone' => $contact->status === ContactSubmission::STATUS_NEW ? 'amber' : 'blue',
                'title' => 'درخواست تماس '.$contact->name,
                'meta' => trim(($contact->need_type ?: 'تماس').' - '.($contact->phone ?: $contact->email ?: 'بدون راه ارتباط')),
                'url' => null,
            ]);

        return $transactions
            ->concat($payments)
            ->concat($vms)
            ->concat($contacts)
            ->sortByDesc('time')
            ->take(8)
            ->values();
    }

    private function readinessScore(int $total, int $online, int $offline, int $failedProvisioning, int $failedBackups, int $staleDeletes): int
    {
        if ($total === 0) {
            return 0;
        }

        $score = $this->percentage($online, $total);
        $score -= min(35, ($offline * 10) + ($failedProvisioning * 5) + ($failedBackups * 3) + ($staleDeletes * 8));

        return max(0, min(100, $score));
    }

    private function percentage(int|float $value, int|float $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($value / $total) * 100)));
    }
}
