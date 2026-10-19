<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationTemplates;
use Illuminate\Notifications\Notification;

class TemplatedNotification extends Notification
{
    public function __construct(public string $event, public array $variables, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $rendered = app(NotificationTemplates::class)->render($this->event, $this->variables + ['name' => $notifiable->name]);

        return ['event' => $this->event, 'title' => $rendered['subject'], 'body' => $rendered['body'], 'url' => $this->url] + $this->variables;
    }
}
