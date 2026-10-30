<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;

class SendMachineCreatedSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $recipientType, public int $recipientId, public string $event, public string $machineId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(NotificationTemplates $templates, KavenegarLookupClient $sms): void
    {
        $class = Relation::getMorphedModel($this->recipientType) ?? $this->recipientType;
        $recipient = $class::find($this->recipientId);
        if (! $recipient || blank($recipient->phone) || AppSetting::smsGateway() !== 'kavenegar'
            || ! $templates->enabled($this->event, 'sms', $recipient)
            || ($recipient instanceof User && (! $recipient->is_active || ! $recipient->allows('virtual-machines.read')))) {
            return;
        }
        $sms->sendLookup($recipient->phone, $templates->all()[$this->event]['sms_template'], $this->machineId);
    }
}
