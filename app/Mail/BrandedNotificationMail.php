<?php

namespace App\Mail;

use App\Services\Notifications\NotificationTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrandedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $event, public array $variables = [], public ?string $actionUrl = null, public ?string $code = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: app(NotificationTemplates::class)->render($this->event, $this->variables)['subject']);
    }

    public function content(): Content
    {
        $templates = app(NotificationTemplates::class);

        return new Content(view: 'emails.branded-notification', text: 'emails.branded-notification-text', with: [
            'notification' => $templates->render($this->event, $this->variables),
            'branding' => $templates->branding(),
        ]);
    }
}
