<?php

namespace App\Jobs;

use App\Mail\BrandedNotificationMail;
use App\Models\AppSetting;
use App\Models\CorporateDelivery;
use App\Models\CorporateRequest;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CorporateDatabaseNotification;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Sms\KavenegarLookupClient;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliverCorporateNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotificationTemplates $templates): void
    {
        $delivery = DB::transaction(function () {
            $row = CorporateDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, ['queued', 'retry'])) {
                return null;
            }
            $row->update(['status' => 'processing', 'attempts' => $row->attempts + 1]);

            return $row;
        });
        if (! $delivery) {
            return;
        }
        $recipient = ($delivery->recipient_type === 'customer' ? Customer::class : User::class)::find($delivery->recipient_id);
        $event = $delivery->event;
        $request = $event->request;
        $admin = $delivery->recipient_type === 'admin';
        $key = $admin ? 'corporate_admin_review' : 'corporate_'.$event->event;
        $channel = $delivery->channel;
        $enabled = (bool) AppSetting::getValue('corporate.'.$channel.'_enabled', true);
        if (! $recipient || ! $enabled || ($admin && (! $recipient->is_active || ! $recipient->allows('corporate.read') || ! $recipient->allows('corporate.review')))
            || ($channel !== 'sms' && ! $templates->enabled($key, $channel, $recipient))) {
            $delivery->update(['status' => 'suppressed']);

            return;
        }
        $url = 'https://'.config('portals.'.($admin ? 'admin' : 'customer').'.domain').($admin ? '/corporate-requests/'.$request->id : '/profile/corporate/'.$request->id);
        $title = $templates->catalog()[$key]['label'];
        $body = 'درخواست شماره '.$request->id.' — '.($admin ? 'آماده بررسی' : (CorporateRequest::statuses()[$event->event === 'submitted' ? 'pending' : $event->event]));
        if (! $admin && filled($event->snapshot['reason'] ?? null)) {
            $body .= "\n".$event->snapshot['reason'];
        }
        if (! $admin && isset($event->snapshot['billing_effective_at'])) {
            $body .= "\nشروع صورتحساب حقوقی: ".Jalali::format(Carbon::parse($event->snapshot['billing_effective_at']));
        }
        try {
            if ($channel === 'in_app') {
                // A deterministic UUID prevents duplicate inbox entries after worker restarts.
                $notification = new CorporateDatabaseNotification(['event' => $key, 'title' => $title, 'body' => $body, 'url' => $url, 'action_label' => 'مشاهده درخواست']);
                $notification->id = sprintf('00000000-0000-4000-8000-%012d', $delivery->id);
                if (! $recipient->notifications()->whereKey($notification->id)->exists()) {
                    $recipient->notify($notification);
                }
            } elseif ($channel === 'email') {
                if (blank($recipient->email)) {
                    $delivery->update(['status' => 'suppressed']);

                    return;
                }
                Mail::mailer('settings_smtp')->to($recipient->email)->send(new BrandedNotificationMail($key, ['name' => $recipient->name, 'title' => $title, 'body' => $body], $url));
            } else {
                if (blank($recipient->phone) || AppSetting::smsGateway() !== 'kavenegar' || (! $admin && ! $recipient->smsNotificationsEnabled())
                    || (! $admin && $event->event === 'submitted' && ! AppSetting::getValue('corporate.submission_sms_enabled', true))) {
                    $delivery->update(['status' => 'suppressed']);

                    return;
                }
                $template = AppSetting::getValue('corporate.template.'.$key, self::smsTemplates()[$key]['name']);
                $entry = app(KavenegarLookupClient::class)->sendLookup($recipient->phone, $template, (string) $request->id);
                $delivery->provider_id = isset($entry['messageid']) ? (string) $entry['messageid'] : null;
                if (! $delivery->provider_id) {
                    $delivery->update(['status' => 'uncertain', 'error' => 'Provider did not return a message ID.']);

                    return;
                }
            }
            $delivery->status = 'sent';
            $delivery->error = null;
            $delivery->save();
        } catch (ConnectionException $exception) {
            $delivery->update(['status' => 'uncertain', 'error' => 'Provider outcome unknown; reconcile before retrying.']);
        } catch (\Throwable $exception) {
            // The existing Lookup client wraps connection errors. Do not blindly repeat uncertain SMS sends.
            $uncertain = $channel === 'sms' && ($exception->getPrevious() instanceof ConnectionException || str_contains($exception->getMessage(), 'اتصال'));
            $delivery->update(['status' => $uncertain ? 'uncertain' : ($delivery->attempts >= 3 ? 'failed' : 'retry'), 'error' => $channel === 'sms' ? 'Kavenegar request failed; check template approval, line and credit.' : 'Notification delivery failed.']);
            if (! $uncertain && $delivery->attempts < 3) {
                throw $exception;
            }
        }
    }

    public static function smsTemplates(): array
    {
        return [
            'corporate_admin_review' => ['name' => 'AviatoCorpAdminReview', 'text' => "آویاتو\nدرخواست احراز هویت حقوقی شماره %token آماده بررسی است.\nلطفاً به پنل مدیریت مراجعه کنید."],
            'corporate_submitted' => ['name' => 'AviatoCorpSubmitted', 'text' => "آویاتو\nدرخواست احراز هویت حقوقی شما به شماره %token ثبت شد.\nنتیجه بررسی از طریق پنل اطلاع‌رسانی می‌شود."],
            'corporate_needs_correction' => ['name' => 'AviatoCorpCorrection', 'text' => "آویاتو\nدرخواست احراز هویت حقوقی شماره %token نیازمند اصلاح است.\nجزئیات را در بخش پروفایل پنل مشاهده کنید."],
            'corporate_approved' => ['name' => 'AviatoCorpApproved', 'text' => "آویاتو\nدرخواست احراز هویت حقوقی شماره %token تأیید شد.\nتاریخ شروع صورتحساب حقوقی را در پروفایل پنل مشاهده کنید."],
            'corporate_rejected' => ['name' => 'AviatoCorpRejected', 'text' => "آویاتو\nدرخواست احراز هویت حقوقی شماره %token رد شد.\nدلیل و جزئیات را در بخش پروفایل پنل مشاهده کنید."],
        ];
    }
}
