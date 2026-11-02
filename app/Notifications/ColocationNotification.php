<?php

namespace App\Notifications;

use App\Models\ColocationService;
use Illuminate\Notifications\Notification;

class ColocationNotification extends Notification
{
    public function __construct(private readonly ColocationService $service, private readonly string $message, private readonly ?int $invoiceId = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => 'colocation', 'project_id' => $this->service->project_id, 'title' => 'کولوکیشن — '.$this->service->name, 'body' => $this->message, 'url' => $this->path()];
    }

    private function path(): string
    {
        return $this->invoiceId
            ? route('customer.colocation.invoices.show', $this->invoiceId, false)
            : route('customer.colocation.show', $this->service->uuid, false);
    }
}
