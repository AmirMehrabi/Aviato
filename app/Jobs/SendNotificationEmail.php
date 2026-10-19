<?php

namespace App\Jobs;

use App\Mail\BrandedNotificationMail;
use App\Services\Notifications\NotificationTemplates;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId, public string $recipientType, public int $recipientId, public array $variables, public ?string $actionUrl) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(NotificationTemplates $templates): void
    {
        $delivery = DB::table('notification_deliveries')->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'suppressed'])) {
            return;
        }
        $class = Relation::getMorphedModel($this->recipientType) ?? $this->recipientType;
        $recipient = $class::find($this->recipientId);
        if (! $recipient || $recipient->email !== $delivery->recipient || ! $templates->enabled($delivery->event, 'email', $recipient)) {
            DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'suppressed', 'updated_at' => now()]);

            return;
        }
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->increment('attempts');
        Mail::to($delivery->recipient)->send(new BrandedNotificationMail($delivery->event, $this->variables, $this->actionUrl));
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
