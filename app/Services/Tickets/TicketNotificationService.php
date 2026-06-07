<?php

namespace App\Services\Tickets;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketDatabaseNotification;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TicketNotificationService
{
    public function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category', 'supportTeam.activeUsers', 'assignee');

        $recipients = $ticket->assignee
            ? collect([$ticket->assignee])
            : ($ticket->supportTeam?->activeUsers ?? User::query()->orderBy('name')->get());

        $recipients->each(function (User $user) use ($ticket): void {
            $this->notifyDatabase($user, $ticket, 'ticket_created', 'تیکت جدید ثبت شد', $ticket->subject);
            $this->notifyEmail($user->email, 'تیکت جدید '.$ticket->number, $this->line($ticket, 'تیکت جدیدی توسط '.$ticket->customer->name.' ثبت شد.'));
            $this->notifySms($user->phone, AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, $ticket, $ticket->customer->name, $ticket->category?->name ?? 'Support');
        });
    }

    public function customerReply(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category', 'supportTeam.activeUsers', 'assignee');

        $recipients = $ticket->assignee
            ? collect([$ticket->assignee])
            : ($ticket->supportTeam?->activeUsers ?? User::query()->orderBy('name')->get());

        $recipients->each(function (User $user) use ($ticket): void {
            $this->notifyDatabase($user, $ticket, 'ticket_customer_reply', 'پاسخ جدید مشتری', $ticket->subject);
            $this->notifyEmail($user->email, 'پاسخ مشتری در '.$ticket->number, $this->line($ticket, 'مشتری در این تیکت پاسخ داد.'));
            $this->notifySms($user->phone, AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE, $ticket, $ticket->customer->name, $ticket->status);
        });
    }

    public function adminReply(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category');
        $customer = $ticket->customer;

        $this->notifyDatabase($customer, $ticket, 'ticket_admin_reply', 'پاسخ پشتیبانی', $ticket->subject);
        $this->notifyEmail($customer->email, 'پاسخ پشتیبانی در '.$ticket->number, $this->line($ticket, 'پشتیبانی به تیکت شما پاسخ داد.'));
        $this->notifySms($customer->phone, AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, $ticket, 'پشتیبانی', $ticket->status);
    }

    public function statusChanged(Ticket $ticket): void
    {
        $ticket->loadMissing('customer');
        $customer = $ticket->customer;

        $this->notifyDatabase($customer, $ticket, 'ticket_status_changed', 'وضعیت تیکت تغییر کرد', Ticket::statuses()[$ticket->status] ?? $ticket->status);
        $this->notifyEmail($customer->email, 'تغییر وضعیت '.$ticket->number, $this->line($ticket, 'وضعیت تیکت شما تغییر کرد.'));
        $this->notifySms($customer->phone, AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE, $ticket, 'پشتیبانی', $ticket->status);
    }

    private function notifyDatabase(User|Customer $recipient, Ticket $ticket, string $type, string $title, string $body): void
    {
        try {
            $recipient->notify(new TicketDatabaseNotification($ticket, $type, $title, $body));
        } catch (Throwable $exception) {
            Log::warning('Ticket database notification failed.', ['ticket_id' => $ticket->id, 'error' => $exception->getMessage()]);
        }
    }

    private function notifyEmail(?string $email, string $subject, string $body): void
    {
        if (! AppSetting::ticketEmailNotificationsEnabled() || blank($email)) {
            return;
        }

        $this->applySmtpConfig();

        try {
            Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
        } catch (Throwable $exception) {
            Log::warning('Ticket email notification failed.', ['email' => $email, 'error' => $exception->getMessage()]);
        }
    }

    private function notifySms(?string $phone, string $templateSettingKey, Ticket $ticket, string $token2, string $token3): void
    {
        if (! AppSetting::ticketSmsNotificationsEnabled() || blank($phone) || AppSetting::smsGateway() !== 'kavenegar') {
            return;
        }

        try {
            app(KavenegarLookupClient::class)->sendLookup(
                $phone,
                (string) AppSetting::getValue($templateSettingKey, ''),
                $ticket->number,
                $token2,
                $token3,
            );
        } catch (Throwable $exception) {
            Log::warning('Ticket SMS notification failed.', ['ticket_id' => $ticket->id, 'error' => $exception->getMessage()]);
        }
    }

    private function applySmtpConfig(): void
    {
        $host = (string) AppSetting::getValue(AppSetting::SMTP_HOST, '');
        if ($host === '') {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) AppSetting::getValue(AppSetting::SMTP_PORT, 587));
        Config::set('mail.mailers.smtp.username', AppSetting::getValue(AppSetting::SMTP_USERNAME, null));
        Config::set('mail.mailers.smtp.password', AppSetting::getValue(AppSetting::SMTP_PASSWORD, null));
        Config::set('mail.mailers.smtp.scheme', AppSetting::getValue(AppSetting::SMTP_ENCRYPTION, null) ?: null);
        Config::set('mail.from.address', AppSetting::getValue(AppSetting::SMTP_FROM_ADDRESS, config('mail.from.address')));
        Config::set('mail.from.name', AppSetting::getValue(AppSetting::SMTP_FROM_NAME, config('mail.from.name')));
    }

    private function line(Ticket $ticket, string $message): string
    {
        return $message."\n\nTicket: {$ticket->number}\nSubject: {$ticket->subject}";
    }
}
