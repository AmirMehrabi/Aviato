<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\VirtualMachine;
use App\Notifications\TemplatedNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class NotificationEventSubscriber
{
    public function register(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            if ($event->channel !== 'database' || ! method_exists($event->notification, 'toArray')) {
                return;
            }
            $data = $event->notification->toArray($event->notifiable);
            $key = $data['event'] ?? '';
            if (! isset(app(NotificationTemplates::class)->all()[$key])) {
                return;
            }
            // Email is independent of the in-app preference.
            $this->email($event->notifiable, $key, $data);
            if (! app(NotificationTemplates::class)->enabled($key, 'in_app', $event->notifiable)) {
                return false;
            }
        });
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'customer' && $event->user instanceof Customer
                && ! (request()->hasSession() && request()->session()->has('impersonated_by_admin_id'))) {
                $this->notify($event->user, 'signed_in', ['ip' => request()->ip(), 'time' => now('Asia/Tehran')->format('Y-m-d H:i').' (تهران)'], route('dashboard'));
            }
        });
        Payment::updated(function (Payment $payment): void {
            if ($payment->wasChanged('status') && $payment->status === Payment::STATUS_SUCCESSFUL) {
                DB::afterCommit(function () use ($payment): void {
                    if ($payment->customer) {
                        $this->notify($payment->customer, 'payment_received', ['amount' => number_format($payment->amount).' '.$payment->currency, 'receipt' => $payment->receiptNumber()], route('customer.wallet.show'));
                    }
                });
            }
        });
        VirtualMachine::updated(function (VirtualMachine $vm): void {
            if ($vm->wasChanged('provisioning_status') && $vm->provisioning_status === 'ready') {
                DB::afterCommit(function () use ($vm): void {
                    if ($vm->customer) {
                        $this->notify($vm->customer, 'server_ready', ['server_name' => $vm->display_name], route('customer.servers.show', $vm));
                    }
                });
            }
        });
    }

    public function notify(object $recipient, string $event, array $variables, string $url): void
    {
        try {
            $recipient->notify(new TemplatedNotification($event, $variables, $url));
        } catch (\Throwable) {
            Log::warning('Optional notification failed.', ['event' => $event]);
        }
    }

    private function email(object $recipient, string $event, array $data): void
    {
        $variables = $data;
        unset($variables['url']);
        $url = $data['url'] ?? null;
        if ($url && str_starts_with($url, '/')) {
            $domain = config($recipient instanceof Customer ? 'portals.customer.domain' : 'portals.admin.domain');
            $url = 'https://'.$domain.$url;
        }
        app(NotificationDispatcher::class)->email($recipient, $event, $variables, $url);
    }
}
