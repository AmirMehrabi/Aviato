<?php

namespace App\Services\Notifications;

use App\Models\Ticket;
use App\Notifications\TicketDatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, unread_count: int, ticket_unread_count: int}
     */
    public function feed(Model $notifiable, string $fallbackUrl, int $limit = 10): array
    {
        $items = $notifiable->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => data_get($notification->data, 'title', 'اعلان'),
                'body' => data_get($notification->data, 'body', ''),
                'url' => data_get($notification->data, 'url', $fallbackUrl),
                'action_label' => data_get($notification->data, 'action_label')
                    ?? ($notification->type === TicketDatabaseNotification::class ? 'مشاهده تیکت' : match (data_get($notification->data, 'event')) {
                        'workspace_wallet_balance' => 'مشاهده فضای کاری',
                        'workspace_added' => 'ورود به فضای کاری',
                        default => 'مشاهده',
                    }),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'unread_count' => $notifiable->unreadNotifications()->count(),
            'ticket_unread_count' => $this->ticketUnreadCount($notifiable),
        ];
    }

    /**
     * @return array{notification_id: string, unread_count: int, ticket_unread_count: int, read_at: string|null}
     */
    public function markRead(Model $notifiable, string $notificationId): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $notifiable->notifications()->whereKey($notificationId)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return [
            'notification_id' => $notification->id,
            'unread_count' => $notifiable->unreadNotifications()->count(),
            'ticket_unread_count' => $this->ticketUnreadCount($notifiable),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }

    /**
     * @return array{unread_count: int, ticket_unread_count: int, marked_count: int}
     */
    public function markAllRead(Model $notifiable): array
    {
        $count = $notifiable->unreadNotifications()->count();

        if ($count > 0) {
            $notifiable->unreadNotifications()->update(['read_at' => now()]);
        }

        return ['unread_count' => 0, 'ticket_unread_count' => 0, 'marked_count' => $count];
    }

    public function ticketUnreadCount(Model $notifiable): int
    {
        return $notifiable->unreadNotifications()->where('type', TicketDatabaseNotification::class)->count();
    }

    public function markTicketRead(Model $notifiable, Ticket $ticket): int
    {
        return $notifiable->unreadNotifications()
            ->where('data->ticket_id', $ticket->id)
            ->update(['read_at' => now()]);
    }
}
