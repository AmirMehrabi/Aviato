<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VmNodeMoveNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $skipped
     */
    public function __construct(
        private readonly int $migrated,
        private readonly int $reconciled,
        private readonly array $skipped,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $failed = count($this->skipped);

        return [
            'event' => 'vm_node_move_finished',
            'title' => 'انتقال Node ماشین‌ها تمام شد',
            'body' => sprintf('%d migrated, %d reconciled, %d skipped.', $this->migrated, $this->reconciled, $failed),
            'url' => route('admin.virtual-machines.index', [], false),
            'migrated' => $this->migrated,
            'reconciled' => $this->reconciled,
            'skipped' => $this->skipped,
        ];
    }
}
