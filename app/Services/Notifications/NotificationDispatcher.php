<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function email(object $recipient, string $event, array $variables, ?string $url = null): void
    {
        if (blank($recipient->email) || ! app(NotificationTemplates::class)->enabled($event, 'email', $recipient)) {
            return;
        }
        // SMTP work runs on dedicated email workers, separate from business jobs.
        $id = null;
        try {
            $id = DB::table('notification_deliveries')->insertGetId([
                'event' => $event, 'recipient' => $recipient->email, 'status' => 'queued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            SendNotificationEmail::dispatch($id, $recipient->getMorphClass(), $recipient->getKey(), $variables + ['name' => $recipient->name], $url)->onConnection(EmailQueue::connection())->onQueue('mail')->afterCommit();
        } catch (\Throwable) {
            if ($id !== null) {
                DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'failed', 'updated_at' => now()]);
            }
            Log::warning('Unable to queue notification email.', ['event' => $event]);
        }
    }
}
