<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly string $event,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $notifiable instanceof Customer
                ? route('customer.tickets.show', $this->ticket, false)
                : route('admin.tickets.show', $this->ticket, false),
        ];
    }
}
