<?php

namespace App\Notifications;

use App\Models\Project;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkspaceWalletBalanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly int $balance,
        private readonly ?float $percent,
        private readonly ?int $threshold,
        private readonly ?int $adminId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $formatted = app(WalletService::class)->format($this->balance);
        $manual = $this->threshold === null;
        $coverage = $this->percent === null ? '.' : " ({$this->percent}٪ از هزینه ماهانه).";

        return [
            'event' => 'workspace_wallet_balance',
            'project_id' => $this->project->id,
            'title' => $manual ? 'موجودی کیف پول فضای کاری' : 'هشدار موجودی کیف پول',
            'body' => $manual
                ? "موجودی قابل استفاده برای «{$this->project->name}»: {$formatted}{$coverage}"
                : "موجودی قابل استفاده برای «{$this->project->name}» به {$this->threshold}٪ از هزینه ماهانه رسیده است: {$formatted}.",
            'url' => route('customer.projects.enter', $this->project, false),
            'balance' => $this->balance,
            'remaining_percent' => $this->percent,
            'threshold_percent' => $this->threshold,
            'sent_by_admin_id' => $this->adminId,
        ];
    }
}
