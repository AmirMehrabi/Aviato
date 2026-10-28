<?php

namespace App\Console\Commands;

use App\Jobs\DeliverCorporateNotification;
use App\Models\CorporateDelivery;
use App\Services\Notifications\EmailQueue;
use Illuminate\Console\Command;

class CorporateNotificationRecovery extends Command
{
    protected $signature = 'corporate:recover-notifications';

    protected $description = 'Recover queued corporate notifications; uncertain sends require manual reconciliation';

    public function handle(): int
    {
        CorporateDelivery::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(10))->update(['status' => 'uncertain', 'error' => 'Worker stopped before recording outcome. Reconcile before retrying.']);
        CorporateDelivery::whereIn('status', ['queued', 'retry'])->where('attempts', '<', 3)->where('updated_at', '<', now()->subMinutes(5))->eachById(function ($delivery) {
            $job = DeliverCorporateNotification::dispatch($delivery->id);
            if ($delivery->channel === 'email') {
                $job->onConnection(EmailQueue::connection())->onQueue('mail');
            }
        });

        return self::SUCCESS;
    }
}
