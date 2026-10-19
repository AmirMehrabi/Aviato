<?php

namespace App\Services\Tickets;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketDatabaseNotification;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class TicketNotificationService
{
    public function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category', 'supportTeam.activeUsers', 'assignee');

        $this->notifySms(
            $ticket->customer->phone,
            AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE,
            $ticket,
            $ticket->customer->name,
            $ticket->category?->name ?? 'پشتیبانی',
        );

        if ($ticket->created_by_user_id) {
            $this->notifyDatabase($ticket->customer, $ticket, 'ticket_created_for_customer', 'تیکت جدید برای شما ثبت شد', $ticket->subject);
        }

        $recipients = $ticket->assignee
            ? collect([$ticket->assignee])
            : ($ticket->supportTeam?->activeUsers ?? User::query()->orderBy('name')->get());

        $recipients->filter(fn (User $user): bool => $user->allows('tickets.read'))->each(function (User $user) use ($ticket): void {
            $this->notifyDatabase($user, $ticket, 'ticket_created', 'تیکت جدید ثبت شد', $ticket->subject);
            $this->notifySms($user->phone, AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, $ticket, $ticket->customer->name, $ticket->category?->name ?? 'پشتیبانی');
        });
    }

    public function customerReply(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category', 'supportTeam.activeUsers', 'assignee');

        $recipients = $ticket->assignee
            ? collect([$ticket->assignee])
            : ($ticket->supportTeam?->activeUsers ?? User::query()->orderBy('name')->get());

        $recipients->filter(fn (User $user): bool => $user->allows('tickets.read'))->each(function (User $user) use ($ticket): void {
            $this->notifyDatabase($user, $ticket, 'ticket_customer_reply', 'پاسخ جدید مشتری', $ticket->subject);
            $this->notifySms($user->phone, AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE, $ticket, $ticket->customer->name, $this->statusLabel($ticket));
        });
    }

    public function adminReply(Ticket $ticket): void
    {
        $ticket->loadMissing('customer', 'category');
        $customer = $ticket->customer;

        $this->notifyDatabase($customer, $ticket, 'ticket_admin_reply', 'پاسخ پشتیبانی', $ticket->subject);
        $this->notifySms($customer->phone, AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, $ticket, $customer->name, $this->statusLabel($ticket));
    }

    public function ticketAssigned(Ticket $ticket, User $assignee): void
    {
        $ticket->load('category');
        if (! $assignee->allows('tickets.read')) {
            return;
        }

        $this->notifyDatabase($assignee, $ticket, 'ticket_assigned', 'تیکت به شما واگذار شد', $ticket->subject);
        $this->notifySms($assignee->phone, AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE, $ticket, $assignee->name, $ticket->category?->name ?? 'پشتیبانی');
    }

    public function statusChanged(Ticket $ticket): void
    {
        $ticket->loadMissing('customer');
        $customer = $ticket->customer;

        $this->notifyDatabase($customer, $ticket, 'ticket_status_changed', 'وضعیت تیکت تغییر کرد', Ticket::statuses()[$ticket->status] ?? $ticket->status);
    }

    private function notifyDatabase(User|Customer $recipient, Ticket $ticket, string $type, string $title, string $body): void
    {
        try {
            $recipient->notify(new TicketDatabaseNotification($ticket, $type, $title, $body));
        } catch (Throwable $exception) {
            Log::warning('Ticket database notification failed.', ['ticket_id' => $ticket->id, 'error' => $exception->getMessage()]);
        }
    }

    private function notifySms(?string $phone, string $templateSettingKey, Ticket $ticket, string $fullName, string $context): void
    {
        if (! AppSetting::ticketSmsNotificationsEnabled() || blank($phone) || AppSetting::smsGateway() !== 'kavenegar') {
            return;
        }

        $template = (string) AppSetting::getValue($templateSettingKey, '');
        if (blank($template)) {
            return;
        }

        try {
            app(KavenegarLookupClient::class)->sendLookup(
                $phone,
                $template,
                $ticket->number,
                token10: $context,
                token20: $fullName,
            );
        } catch (Throwable $exception) {
            Log::warning('Ticket SMS notification failed.', ['ticket_id' => $ticket->id, 'error' => $exception->getMessage()]);
        }
    }

    private function line(Ticket $ticket, string $message): string
    {
        return $message."\n\nTicket: {$ticket->number}\nSubject: {$ticket->subject}";
    }

    private function statusLabel(Ticket $ticket): string
    {
        return Ticket::statuses()[$ticket->status] ?? $ticket->status;
    }
}
