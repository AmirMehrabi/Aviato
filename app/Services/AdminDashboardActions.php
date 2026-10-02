<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProxmoxServer;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmUpgradeOrder;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardActions
{
    public const VISIBLE_LIMIT = 8;

    public function __construct(private readonly AdminDashboardFinance $finance) {}

    public function snapshot(Collection $dismissedKeys, int $page = 1, ?string $category = null, ?User $user = null): array
    {
        $queries = $this->issueQueries($user);
        $counts = collect($queries)->map(fn (Builder $query): int => (clone $query)->count());
        $categoryTypes = match ($category) {
            'servers' => ['server-offline'],
            'machines' => ['vm-provisioning', 'vm-delete'],
            'tickets' => ['ticket'],
            'payments' => ['payment-reconciliation', 'payment-pending'],
            default => array_keys($queries),
        };
        $queueQueries = array_intersect_key($queries, array_flip($categoryTypes));
        $queueTotal = collect($categoryTypes)->sum(fn (string $type): int => ($counts[$type] ?? 0));
        $dismissed = $dismissedKeys->flip();
        $hiddenCount = 0;

        if ($dismissed->isNotEmpty()) {
            foreach ($queueQueries as $type => $query) {
                foreach ((clone $query)->select(['id', 'updated_at'])->cursor() as $record) {
                    if ($dismissed->has($this->warningKey($type, $record->id, $record->updated_at?->getTimestamp()))) {
                        $hiddenCount++;
                    }
                }
            }
        }

        // A category can fill the queue by itself. Include room for dismissed rows
        // so they cannot push every visible row beyond the candidate limit.
        $candidateLimit = self::VISIBLE_LIMIT * $page + $hiddenCount;
        $items = collect();

        foreach ($queueQueries as $type => $query) {
            $items = $items->concat($this->candidates($type, $query, $candidateLimit));
        }

        $visible = $items
            ->reject(fn (array $item): bool => $dismissed->has($item['key']))
            ->sort(function (array $a, array $b): int {
                return ($b['rank'] <=> $a['rank'])
                    ?: ($a['occurred_at']->getTimestamp() <=> $b['occurred_at']->getTimestamp())
                    ?: strcmp($a['key'], $b['key']);
            })
            ->values();

        return [
            'items' => $visible->slice(($page - 1) * self::VISIBLE_LIMIT, self::VISIBLE_LIMIT)->values(),
            'total_count' => $queueTotal,
            'hidden_count' => $hiddenCount,
            'remaining_count' => max(0, $queueTotal - $hiddenCount - $page * self::VISIBLE_LIMIT),
            'page' => $page,
            'category' => $category,
            'filter_label' => match ($category) {
                'servers' => 'سرورهای آفلاین',
                'machines' => 'ماشین‌های نیازمند بررسی',
                'tickets' => 'تیکت‌های در انتظار پاسخ',
                'payments' => 'پرداخت‌های نیازمند بررسی',
                default => null,
            },
            'health' => array_values(array_filter([
                [
                    'label' => 'سرور آفلاین',
                    'count' => ($counts['server-offline'] ?? 0),
                    'category' => 'servers',
                    'url' => route('admin.dashboard', ['category' => 'servers']),
                ],
                [
                    'label' => 'ماشین نیازمند بررسی',
                    'count' => ($counts['vm-provisioning'] ?? 0) + ($counts['vm-delete'] ?? 0),
                    'category' => 'machines',
                    'url' => route('admin.dashboard', ['category' => 'machines']),
                ],
                [
                    'label' => 'پرداخت نیازمند بررسی',
                    'count' => ($counts['payment-reconciliation'] ?? 0) + ($counts['payment-pending'] ?? 0),
                    'category' => 'payments',
                    'url' => route('admin.dashboard', ['category' => 'payments']),
                ],
                [
                    'label' => 'تیکت در انتظار پاسخ',
                    'count' => ($counts['ticket'] ?? 0),
                    'category' => 'tickets',
                    'url' => route('admin.dashboard', ['category' => 'tickets']),
                ],
            ], fn (array $card): bool => match ($card['category']) {
                'servers' => isset($queries['server-offline']),
                'machines' => isset($queries['vm-provisioning']),
                'payments' => isset($queries['payment-pending']),
                'tickets' => isset($queries['ticket']),
            })),
        ];
    }

    public function hasActiveKey(string $key, ?User $user = null): bool
    {
        foreach ($this->issueQueries($user) as $type => $query) {
            foreach ((clone $query)->select(['id', 'updated_at'])->cursor() as $record) {
                if (hash_equals($key, $this->warningKey($type, $record->id, $record->updated_at?->getTimestamp()))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function issueQueries(?User $user = null): array
    {
        $staleDeletion = VirtualMachine::query()
            ->notDeleted()
            ->where('status', VirtualMachine::STATUS_DELETING)
            ->where(function (Builder $query): void {
                $query->whereNotNull('delete_failed_at')
                    ->orWhere('delete_started_at', '<=', now()->subMinutes(15))
                    ->orWhere('delete_requested_at', '<=', now()->subMinutes(15));
            });

        $queries = [
            'payment-reconciliation' => $this->finance->uncreditedPayments(),
            'payment-pending' => Payment::query()->where('status', Payment::STATUS_PENDING)
                ->where('created_at', '<=', now()->subMinutes(AdminDashboardFinance::PENDING_AGE_MINUTES)),
            'vm-provisioning' => VirtualMachine::query()->notDeleted()
                ->where('status', '!=', VirtualMachine::STATUS_DELETING)
                ->where('provisioning_status', VirtualMachine::PROVISION_FAILED),
            'vm-delete' => $staleDeletion,
            'server-offline' => ProxmoxServer::query()
                ->where('connection_status', ProxmoxServer::CONNECTION_OFFLINE),
            'upgrade' => VmUpgradeOrder::query()
                ->whereIn('status', [VmUpgradeOrder::STATUS_FAILED, VmUpgradeOrder::STATUS_RECONCILIATION_REQUIRED]),
            'backup' => VmBackup::query()
                ->where('status', VmBackup::STATUS_FAILED)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('vm_backups as newer_backup')
                        ->whereColumn('newer_backup.virtual_machine_id', 'vm_backups.virtual_machine_id')
                        ->whereColumn('newer_backup.id', '>', 'vm_backups.id');
                }),
            'server-sync' => ProxmoxServer::query()
                ->where('connection_status', '!=', ProxmoxServer::CONNECTION_OFFLINE)
                ->where('sync_status', ProxmoxServer::SYNC_FAILED),
            'ticket' => Ticket::query()->where('status', Ticket::STATUS_OPEN),
            'wallet' => Wallet::query()->where(function (Builder $query): void {
                $query->where('balance', '<', 0)->orWhere('is_locked', true);
            }),
        ];
        if ($user) {
            $queries = array_filter($queries, fn (Builder $query, string $type): bool => match ($type) {
                'payment-reconciliation', 'payment-pending' => $user->allows('billing.read'),
                'wallet' => $user->allows('billing.read') && $user->allows('customers.read'),
                'ticket' => $user->allows('tickets.read'),
                'server-offline', 'server-sync' => $user->allows('infrastructure.read'),
                default => $user->allows('virtual-machines.read'),
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $queries;
    }

    private function candidates(string $type, Builder $query, int $limit): Collection
    {
        $relations = match ($type) {
            'vm-provisioning', 'vm-delete' => ['customer'],
            'upgrade', 'backup' => ['virtualMachine.customer'],
            'ticket', 'wallet' => ['customer'],
            'payment-reconciliation', 'payment-pending' => ['customer'],
            default => [],
        };

        if ($type === 'ticket') {
            $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END");
            $query->orderByRaw('COALESCE(last_customer_reply_at, created_at)');
        } elseif ($type === 'upgrade') {
            $query->orderByRaw("CASE status WHEN 'failed' THEN 1 ELSE 2 END");
        } elseif ($type === 'wallet') {
            $query->orderByDesc('is_locked');
        } elseif ($type === 'vm-delete') {
            $query->orderByRaw('COALESCE(delete_failed_at, delete_started_at, delete_requested_at, updated_at)');
        } elseif ($type === 'server-offline') {
            $query->orderByRaw('COALESCE(last_seen_at, updated_at)');
        } elseif ($type === 'backup') {
            $query->orderByRaw('COALESCE(finished_at, updated_at)');
        } elseif (str_starts_with($type, 'payment-')) {
            $query->orderBy($type === 'payment-pending' ? 'created_at' : 'paid_at');
        }

        return $query->with($relations)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($record): array => $this->item($type, $record));
    }

    private function item(string $type, $record): array
    {
        $occurredAt = match ($type) {
            'ticket' => $record->last_customer_reply_at ?? $record->created_at,
            'vm-delete' => $record->delete_failed_at ?? $record->delete_started_at ?? $record->delete_requested_at ?? $record->updated_at,
            'server-offline' => $record->last_seen_at ?? $record->updated_at,
            'backup' => $record->finished_at ?? $record->updated_at,
            'payment-reconciliation' => $record->paid_at,
            'payment-pending' => $record->created_at,
            default => $record->updated_at,
        };
        $occurredAt ??= now();

        $details = match ($type) {
            'payment-reconciliation' => [
                'rank' => 98,
                'category' => 'پرداخت موفق بدون اعتبار کیف پول',
                'title' => $record->customer?->name ?: 'مشتری شماره '.$record->customer_id,
                'meta' => $record->provider.' · #'.$record->id,
                'url' => route('admin.billing.payments.show', $record),
                'action' => 'تطبیق پرداخت',
            ],
            'payment-pending' => [
                'rank' => 78,
                'category' => 'پرداخت در انتظار طولانی',
                'title' => $record->customer?->name ?: 'مشتری شماره '.$record->customer_id,
                'meta' => $record->provider.' · #'.$record->id,
                'url' => route('admin.billing.payments.show', $record),
                'action' => 'بررسی پرداخت',
            ],
            'vm-provisioning' => [
                'rank' => 100,
                'category' => 'آماده‌سازی ناموفق',
                'title' => $record->display_name,
                'meta' => $record->customer?->name ?: 'بدون مشتری',
                'url' => route('admin.virtual-machines.show', $record),
                'action' => 'بررسی ماشین',
            ],
            'vm-delete' => [
                'rank' => 95,
                'category' => 'حذف متوقف‌شده',
                'title' => $record->display_name,
                'meta' => $record->customer?->name ?: 'بدون مشتری',
                'url' => route('admin.virtual-machines.show', $record),
                'action' => 'بررسی حذف',
            ],
            'server-offline' => [
                'rank' => 90,
                'category' => 'سرور آفلاین',
                'title' => $record->name,
                'meta' => $record->datacenter ?: 'بدون دیتاسنتر',
                'url' => route('admin.proxmox-servers.show', $record),
                'action' => 'بررسی سرور',
            ],
            'upgrade' => [
                'rank' => $record->status === VmUpgradeOrder::STATUS_FAILED ? 85 : 82,
                'category' => $record->status === VmUpgradeOrder::STATUS_FAILED ? 'ارتقای ناموفق' : 'ارتقا نیازمند تطبیق',
                'title' => $record->virtualMachine?->display_name ?: 'درخواست شماره '.$record->id,
                'meta' => $record->virtualMachine?->customer?->name ?: 'بدون مشتری',
                'url' => $record->virtualMachine ? route('admin.virtual-machines.show', $record->virtualMachine).'#upgrade-history' : route('admin.virtual-machines.index'),
                'action' => 'بررسی ارتقا',
            ],
            'backup' => [
                'rank' => 80,
                'category' => 'بکاپ ناموفق',
                'title' => $record->virtualMachine?->display_name ?: 'بکاپ شماره '.$record->id,
                'meta' => ($record->virtualMachine?->customer?->name ?: 'بدون مشتری').' · آخرین بکاپ این ماشین ناموفق بوده است',
                'url' => $record->virtualMachine ? route('admin.virtual-machines.show', $record->virtualMachine).'#backup-status' : route('admin.virtual-machines.index'),
                'action' => 'بررسی بکاپ',
            ],
            'server-sync' => [
                'rank' => 75,
                'category' => 'همگام‌سازی ناموفق',
                'title' => $record->name,
                'meta' => $record->datacenter ?: 'بدون دیتاسنتر',
                'url' => route('admin.proxmox-servers.show', $record),
                'action' => 'بررسی همگام‌سازی',
            ],
            'ticket' => [
                'rank' => match ($record->priority) {
                    Ticket::PRIORITY_URGENT => 88,
                    Ticket::PRIORITY_HIGH => 72,
                    default => 55,
                },
                'category' => 'تیکت در انتظار پاسخ',
                'title' => $record->subject,
                'meta' => ($record->customer?->name ?: 'بدون مشتری').' · #'.$record->number,
                'url' => route('admin.tickets.show', $record),
                'action' => 'پاسخ به تیکت',
            ],
            'wallet' => [
                'rank' => $record->is_locked ? 65 : 60,
                'category' => $record->is_locked ? 'کیف پول قفل‌شده' : 'کیف پول منفی',
                'title' => $record->customer?->name ?: 'مشتری شماره '.$record->customer_id,
                'meta' => $record->lock_reason ?: 'نیازمند بررسی مالی',
                'url' => $record->customer ? route('admin.customers.show', $record->customer) : route('admin.customers.index'),
                'action' => 'بررسی حساب',
            ],
        };

        return [
            'key' => $this->warningKey($type, $record->id, $record->updated_at?->getTimestamp()),
            'rank' => $details['rank'],
            'severity' => $details['rank'] >= 85 ? 'critical' : 'attention',
            'category' => $details['category'],
            'title' => $details['title'],
            'meta' => $details['meta'],
            'url' => $details['url'],
            'action' => $details['action'],
            'occurred_at' => $occurredAt,
            'age' => $occurredAt->diffForHumans(),
        ];
    }

    private function warningKey(string $type, int $id, ?int $updatedAt): string
    {
        if (in_array($type, ['server-offline', 'server-sync'], true)) {
            $type = 'proxmox-sync';
        }

        return hash('sha256', implode('|', [$type, $id, $updatedAt ?? 0]));
    }
}
