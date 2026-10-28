<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\Payment;
use App\Models\VirtualMachine;
use App\Notifications\CorporateDatabaseNotification;
use App\Notifications\TemplatedNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Crypt;
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
            // Corporate deliveries have their own durable per-channel outbox.
            if ($event->notification instanceof CorporateDatabaseNotification) {
                return;
            }
            // Email is independent of the in-app preference.
            if (! $event->notification instanceof TemplatedNotification || $event->notification->sendEmail) {
                $this->email($event->notifiable, $key, $data);
            }
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
                        $event = $payment->domain_order_id ? 'payment_received' : 'wallet_charged';
                        $url = $payment->domain_order_id
                            ? route('customer.domains.orders.show', DomainOrder::findOrFail($payment->domain_order_id))
                            : route('customer.wallet.show');
                        $this->notify($payment->customer, $event, ['amount' => number_format($payment->amount).' '.$payment->currency, 'receipt' => $payment->receiptNumber()], $url);
                    }
                });
            }
        });
        VirtualMachine::updated(function (VirtualMachine $vm): void {
            if ($vm->wasChanged('provisioning_status') && $vm->provisioning_status === 'ready') {
                DB::afterCommit(function () use ($vm): void {
                    if ($vm->customer) {
                        if ($vm->cloudImage?->os_family === 'router_os') {
                            $this->notify($vm->customer, 'routeros_vm_created', ['server_name' => $vm->display_name], route('customer.tickets.index'));

                            return;
                        }
                        $variables = [
                            'server_name' => $vm->display_name,
                            'server_ip' => $vm->ip_address ?: 'در پنل بررسی کنید',
                            'username' => $vm->login_username ?: 'در پنل بررسی کنید',
                        ];
                        $url = route('customer.servers.show', $vm);
                        $this->notify($vm->customer, 'server_ready', $variables, $url, false);
                        $password = $vm->notificationPasswordEncrypted !== null
                            ? Crypt::decryptString($vm->notificationPasswordEncrypted)
                            : ($vm->retain_login_password ? $vm->login_password : null);
                        app(NotificationDispatcher::class)->email($vm->customer, 'server_ready', $variables + [
                            'initial_password' => $password,
                            'password_notice' => filled($password) ? '' : (filled($vm->ssh_public_key)
                                ? 'برای ورود از کلید SSH انتخاب‌شده هنگام ساخت سرور استفاده کنید.'
                                : 'رمز اولیه در دسترس نیست؛ اطلاعات ورود یا گزینه بازنشانی رمز را در پنل بررسی کنید.'),
                        ], $url);
                    }
                });
            }
        });
    }

    public function notify(object $recipient, string $event, array $variables, string $url, bool $sendEmail = true): void
    {
        try {
            $recipient->notify(new TemplatedNotification($event, $variables, $url, $sendEmail));
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
