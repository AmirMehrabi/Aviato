<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\AdminDashboardWarningDismissal;
use App\Models\Payment;
use App\Models\ProxmoxServer;
use App\Models\Ticket;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmUpgradeOrder;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user('admin')->role !== AdminRole::Admin) {
            return redirect()->route(AdminAccess::landingRoute($request->user('admin')));
        }

        $vmBase = VirtualMachine::query()->notDeleted();
        $vmCounts = (clone $vmBase)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as running', [VirtualMachine::STATUS_RUNNING])
            ->selectRaw('COALESCE(SUM(CASE WHEN provisioning_status = ? THEN 1 ELSE 0 END), 0) as provisioning_pending', [VirtualMachine::PROVISION_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN provisioning_status = ? THEN 1 ELSE 0 END), 0) as provisioning_failed', [VirtualMachine::PROVISION_FAILED])
            ->first();
        $staleDeleteAttempts = (clone $vmBase)
            ->where('status', VirtualMachine::STATUS_DELETING)
            ->where(function ($query): void {
                $query->whereNotNull('delete_failed_at')
                    ->orWhere('delete_started_at', '<=', now()->subMinutes(15))
                    ->orWhere('delete_requested_at', '<=', now()->subMinutes(15));
            })
            ->count();

        $proxmoxCounts = ProxmoxServer::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN connection_status = ? THEN 1 ELSE 0 END), 0) as online', [ProxmoxServer::CONNECTION_ONLINE])
            ->selectRaw('COALESCE(SUM(CASE WHEN connection_status = ? THEN 1 ELSE 0 END), 0) as offline', [ProxmoxServer::CONNECTION_OFFLINE])
            ->selectRaw('COALESCE(SUM(CASE WHEN sync_status IN (?, ?) THEN 1 ELSE 0 END), 0) as sync_attention', [ProxmoxServer::SYNC_PENDING, ProxmoxServer::SYNC_FAILED])
            ->first();
        $walletCounts = Wallet::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END), 0) as negative')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END), 0) as locked')
            ->selectRaw('ABS(COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END), 0)) as negative_total')
            ->first();

        $paymentPeriodStart = now()->subDays(29)->startOfDay();
        $paymentPeriodEnd = now();
        $paymentPeriod = Payment::query()->whereBetween('created_at', [$paymentPeriodStart, $paymentPeriodEnd]);
        $successfulPaymentQuery = Payment::query()
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$paymentPeriodStart, $paymentPeriodEnd]);
        $successfulPaymentCount = (clone $successfulPaymentQuery)->count();
        $successfulPaymentAmount = (int) (clone $successfulPaymentQuery)->sum('amount');
        $paymentAttemptCount = (clone $paymentPeriod)->count();
        $failedPaymentCount = (clone $paymentPeriod)
            ->whereIn('status', [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])
            ->count();
        $pendingPayments = Payment::query()->where('status', Payment::STATUS_PENDING)->count();
        $paymentSuccessRate = $paymentAttemptCount > 0
            ? (int) round(($successfulPaymentCount / $paymentAttemptCount) * 100)
            : 0;
        $todaySuccessfulPaymentAmount = (int) Payment::query()
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->startOfDay())
            ->sum('amount');
        $recentGatewayPayments = Payment::query()
            ->with('customer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'customer' => $payment->customer?->name ?: 'بدون مشتری',
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'provider' => $payment->provider,
                'reference' => $payment->provider_reference ?: $payment->authority,
                'url' => route('admin.billing.payments.show', $payment),
            ]);

        $ticketSource = Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING]);
        $ticketStats = (clone $ticketSource)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END), 0) as urgent', [Ticket::PRIORITY_URGENT])
            ->selectRaw('COALESCE(SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END), 0) as high', [Ticket::PRIORITY_HIGH])
            ->first();
        $recentTickets = (clone $ticketSource)
            ->with('customer')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Ticket $ticket): array => [
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'customer' => $ticket->customer?->name ?? '—',
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'status_label' => Ticket::statuses()[$ticket->status] ?? $ticket->status,
                'time' => $ticket->last_activity_at?->diffForHumans() ?? $ticket->created_at->diffForHumans(),
            ]);

        $allWarnings = $this->attentionItems();
        $dismissedKeys = AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->pluck('warning_key');
        $visibleWarnings = $allWarnings
            ->reject(fn (array $warning): bool => $dismissedKeys->contains($warning['key']))
            ->values();
        $dismissedActiveCount = $allWarnings
            ->whereIn('key', $dismissedKeys)
            ->count();

        $proxmoxTotal = (int) ($proxmoxCounts?->total ?? 0);
        $proxmoxOnline = (int) ($proxmoxCounts?->online ?? 0);
        $proxmoxOffline = (int) ($proxmoxCounts?->offline ?? 0);
        $proxmoxSyncAttention = (int) ($proxmoxCounts?->sync_attention ?? 0);
        $totalVms = (int) ($vmCounts?->total ?? 0);
        $runningVms = (int) ($vmCounts?->running ?? 0);
        $pendingProvisioning = (int) ($vmCounts?->provisioning_pending ?? 0);
        $failedProvisioning = (int) ($vmCounts?->provisioning_failed ?? 0);
        $ticketsTotal = (int) ($ticketStats?->total ?? 0);
        $urgentTickets = (int) ($ticketStats?->urgent ?? 0);

        return view('admin.dashboard', [
            'statusStrip' => [
                [
                    'label' => 'زیرساخت',
                    'value' => "{$proxmoxOnline}/{$proxmoxTotal}",
                    'sub' => $proxmoxOffline > 0 ? $proxmoxOffline.' آفلاین' : ($proxmoxSyncAttention > 0 ? $proxmoxSyncAttention.' نیازمند همگام‌سازی' : 'همه آنلاین'),
                    'tone' => $proxmoxOffline > 0 ? 'red' : ($proxmoxSyncAttention > 0 ? 'amber' : 'green'),
                    'url' => route('admin.proxmox-servers.index'),
                ],
                [
                    'label' => 'ماشین‌های مجازی',
                    'value' => "{$runningVms}/{$totalVms}",
                    'sub' => $failedProvisioning > 0 ? $failedProvisioning.' آماده‌سازی ناموفق' : ($pendingProvisioning > 0 ? $pendingProvisioning.' در صف ساخت' : 'بدون خطای ساخت'),
                    'tone' => $failedProvisioning > 0 ? 'red' : ($pendingProvisioning > 0 ? 'amber' : 'green'),
                    'url' => route('admin.virtual-machines.index'),
                ],
                [
                    'label' => 'تیکت‌ها',
                    'value' => $ticketsTotal,
                    'sub' => $urgentTickets > 0 ? $urgentTickets.' فوری' : 'بدون تیکت فوری',
                    'tone' => $urgentTickets > 0 ? 'red' : ($ticketsTotal > 0 ? 'amber' : 'green'),
                    'url' => route('admin.tickets.index'),
                ],
                [
                    'label' => 'پرداخت‌ها',
                    'value' => $pendingPayments + $failedPaymentCount,
                    'sub' => $pendingPayments.' در انتظار · '.$failedPaymentCount.' ناموفق',
                    'tone' => $failedPaymentCount > 0 ? 'red' : ($pendingPayments > 0 ? 'amber' : 'green'),
                    'url' => route('admin.billing.payments.index'),
                ],
                [
                    'label' => 'هشدارهای فعال',
                    'value' => $allWarnings->count(),
                    'sub' => $allWarnings->where('tone', 'red')->count().' بحرانی',
                    'tone' => $allWarnings->where('tone', 'red')->isNotEmpty() ? 'red' : ($allWarnings->isNotEmpty() ? 'amber' : 'green'),
                    'url' => '#critical-warnings',
                ],
            ],
            'criticalAlerts' => $visibleWarnings,
            'activeWarningCount' => $allWarnings->count(),
            'dismissedActiveCount' => $dismissedActiveCount,
            'serverHealth' => $this->serverRows(),
            'paymentSummary' => [
                'successful_amount' => $successfulPaymentAmount,
                'successful_count' => $successfulPaymentCount,
                'today_amount' => $todaySuccessfulPaymentAmount,
                'pending_count' => $pendingPayments,
                'failed_count' => $failedPaymentCount,
                'success_rate' => $paymentSuccessRate,
                'negative_wallets' => (int) ($walletCounts?->negative ?? 0),
                'negative_total' => $this->wallets->format((int) ($walletCounts?->negative_total ?? 0)),
                'locked_wallets' => (int) ($walletCounts?->locked ?? 0),
            ],
            'recentGatewayPayments' => $recentGatewayPayments,
            'recentTickets' => $recentTickets,
            'wallets' => $this->wallets,
        ]);
    }

    public function dismissWarning(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warning_key' => ['required', 'string', 'size:64'],
        ]);
        $warning = $this->attentionItems()->firstWhere('key', $data['warning_key']);

        if (! $warning) {
            return back()->with('status', 'این هشدار دیگر فعال نیست.');
        }

        AdminDashboardWarningDismissal::query()->updateOrCreate([
            'user_id' => $request->user('admin')->id,
            'warning_key' => $data['warning_key'],
        ]);

        return back()->with('status', 'هشدار برای حساب شما بسته شد.');
    }

    public function restoreWarnings(Request $request): RedirectResponse
    {
        AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->delete();

        return back()->with('status', 'هشدارهای بسته‌شده دوباره نمایش داده شدند.');
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
                'key' => $this->warningKey('vm-provisioning', $vm->id, $vm->updated_at?->getTimestamp()),
                'priority' => 100,
                'tone' => 'red',
                'label' => 'آماده‌سازی ناموفق',
                'title' => $vm->display_name,
                'meta' => ($vm->customer?->name ?: 'بدون مشتری').' · '.($vm->proxmoxServer?->name ?: 'بدون سرور میزبان'),
                'details_url' => route('admin.virtual-machines.show', $vm),
                'action_url' => route('admin.virtual-machines.retry-provisioning', $vm),
                'action_method' => 'POST',
                'action_label' => 'تلاش دوباره',
                'confirmation' => 'آماده‌سازی این ماشین دوباره در صف قرار بگیرد؟',
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
                'key' => $this->warningKey('vm-delete', $vm->id, $vm->updated_at?->getTimestamp()),
                'priority' => 95,
                'tone' => 'red',
                'label' => 'حذف متوقف‌شده',
                'title' => $vm->display_name,
                'meta' => $vm->delete_error ?: 'فرایند حذف بیش از ۱۵ دقیقه طول کشیده است',
                'details_url' => route('admin.virtual-machines.show', $vm),
                'action_url' => route('admin.virtual-machines.destroy', $vm),
                'action_method' => 'DELETE',
                'action_label' => 'تلاش دوباره برای حذف',
                'confirmation' => 'فرایند حذف این ماشین دوباره در صف قرار بگیرد؟',
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
                'key' => $this->warningKey('proxmox-sync', $server->id, $server->updated_at?->getTimestamp()),
                'priority' => $server->connection_status === ProxmoxServer::CONNECTION_OFFLINE ? 90 : 70,
                'tone' => $server->connection_status === ProxmoxServer::CONNECTION_OFFLINE ? 'red' : 'amber',
                'label' => $server->connection_status === ProxmoxServer::CONNECTION_OFFLINE ? 'سرور آفلاین' : 'همگام‌سازی ناقص',
                'title' => $server->name,
                'meta' => $server->sync_error ?: $this->serverStatusMeta($server),
                'details_url' => route('admin.proxmox-servers.show', $server),
                'action_url' => route('admin.proxmox-servers.sync', $server),
                'action_method' => 'POST',
                'action_label' => 'همگام‌سازی',
                'confirmation' => null,
            ]));

        VmBackup::query()
            ->with('virtualMachine.customer')
            ->where('status', VmBackup::STATUS_FAILED)
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VmBackup $backup) => $items->push([
                'key' => $this->warningKey('backup-failed', $backup->id, $backup->updated_at?->getTimestamp()),
                'priority' => 80,
                'tone' => 'amber',
                'label' => 'بکاپ ناموفق',
                'title' => $backup->virtualMachine?->display_name ?: 'بکاپ شماره '.$backup->id,
                'meta' => $backup->error ?: ($backup->storage ?: 'جزئیات خطا ثبت نشده است'),
                'details_url' => $backup->virtualMachine ? route('admin.virtual-machines.show', $backup->virtualMachine) : route('admin.virtual-machines.index'),
                'action_url' => null,
                'action_method' => null,
                'action_label' => null,
                'confirmation' => null,
            ]));

        VmUpgradeOrder::query()
            ->with('virtualMachine.customer')
            ->whereIn('status', [VmUpgradeOrder::STATUS_PENDING, VmUpgradeOrder::STATUS_APPLYING, VmUpgradeOrder::STATUS_FAILED])
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn (VmUpgradeOrder $order) => $items->push([
                'key' => $this->warningKey('upgrade', $order->id, $order->updated_at?->getTimestamp()),
                'priority' => $order->status === VmUpgradeOrder::STATUS_FAILED ? 85 : 55,
                'tone' => $order->status === VmUpgradeOrder::STATUS_FAILED ? 'red' : 'amber',
                'label' => $order->status === VmUpgradeOrder::STATUS_FAILED ? 'ارتقای ناموفق' : 'ارتقای در حال انجام',
                'title' => $order->virtualMachine?->display_name ?: 'درخواست ارتقا شماره '.$order->id,
                'meta' => $order->failure_reason ?: $this->wallets->format((int) $order->estimated_monthly_delta).' تغییر هزینه ماهانه',
                'details_url' => $order->virtualMachine ? route('admin.virtual-machines.show', $order->virtualMachine) : route('admin.virtual-machines.index'),
                'action_url' => null,
                'action_method' => null,
                'action_label' => null,
                'confirmation' => null,
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
                'key' => $this->warningKey('wallet', $wallet->id, $wallet->updated_at?->getTimestamp()),
                'priority' => $wallet->balance < 0 ? 75 : 45,
                'tone' => $wallet->balance < 0 ? 'red' : 'amber',
                'label' => $wallet->is_locked ? 'کیف پول قفل‌شده' : 'کیف پول منفی',
                'title' => $wallet->customer?->name ?: 'مشتری شماره '.$wallet->customer_id,
                'meta' => $this->wallets->format((int) $wallet->balance),
                'details_url' => $wallet->customer ? route('admin.customers.show', $wallet->customer) : route('admin.customers.index'),
                'action_url' => null,
                'action_method' => null,
                'action_label' => null,
                'confirmation' => null,
            ]));

        return $items->sortByDesc('priority')->take(10)->values();
    }

    private function serverRows(): Collection
    {
        return ProxmoxServer::query()
            ->withCount(['virtualMachines as live_vms_count' => fn ($query) => $query->notDeleted()])
            ->orderByRaw("CASE connection_status WHEN 'offline' THEN 1 WHEN 'unknown' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE sync_status WHEN 'failed' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->limit(6)
            ->get()
            ->map(fn (ProxmoxServer $server): array => [
                'name' => $server->name,
                'status' => match ($server->connection_status) {
                    ProxmoxServer::CONNECTION_ONLINE => 'آنلاین',
                    ProxmoxServer::CONNECTION_OFFLINE => 'آفلاین',
                    default => 'نامشخص',
                },
                'status_class' => match ($server->connection_status) {
                    ProxmoxServer::CONNECTION_ONLINE => 'bg-emerald-50 text-emerald-700',
                    ProxmoxServer::CONNECTION_OFFLINE => 'bg-red-50 text-red-700',
                    default => 'bg-amber-50 text-amber-700',
                },
                'dot_class' => match ($server->connection_status) {
                    ProxmoxServer::CONNECTION_ONLINE => 'bg-emerald-500',
                    ProxmoxServer::CONNECTION_OFFLINE => 'bg-red-500',
                    default => 'bg-amber-500',
                },
                'detail' => ($server->datacenter ?: 'بدون دیتاسنتر').' · '.$server->live_vms_count.' ماشین',
                'sync' => match ($server->sync_status) {
                    ProxmoxServer::SYNC_SYNCED => 'همگام',
                    ProxmoxServer::SYNC_FAILED => 'همگام‌سازی ناموفق',
                    default => 'در انتظار همگام‌سازی',
                },
                'synced_at' => $server->synced_at?->diffForHumans() ?: 'هنوز همگام نشده',
                'url' => route('admin.proxmox-servers.show', $server),
                'sync_url' => route('admin.proxmox-servers.sync', $server),
            ]);
    }

    private function warningKey(string $type, int $id, ?int $updatedAt): string
    {
        return hash('sha256', implode('|', [$type, $id, $updatedAt ?? 0]));
    }

    private function serverStatusMeta(ProxmoxServer $server): string
    {
        $connection = match ($server->connection_status) {
            ProxmoxServer::CONNECTION_ONLINE => 'اتصال آنلاین',
            ProxmoxServer::CONNECTION_OFFLINE => 'اتصال آفلاین',
            default => 'وضعیت اتصال نامشخص',
        };
        $sync = match ($server->sync_status) {
            ProxmoxServer::SYNC_SYNCED => 'همگام',
            ProxmoxServer::SYNC_FAILED => 'همگام‌سازی ناموفق',
            default => 'در انتظار همگام‌سازی',
        };

        return $connection.' · '.$sync;
    }
}
