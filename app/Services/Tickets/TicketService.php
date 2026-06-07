<?php

namespace App\Services\Tickets;

use App\Models\Customer;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketService
{
    public function __construct(
        private readonly TicketAssignmentService $assignments,
        private readonly TicketNotificationService $notifications,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function create(Customer $customer, array $data, Customer|User $actor, array $attachments = []): Ticket
    {
        $ticket = DB::transaction(function () use ($customer, $data, $actor, $attachments): Ticket {
            $category = TicketCategory::query()->with('supportTeam')->find($data['ticket_category_id'] ?? null);
            $team = $this->assignments->teamFor($category);
            $assignee = isset($data['assigned_user_id'])
                ? User::query()->find($data['assigned_user_id'])
                : $this->assignments->autoAssign($category);

            $ticket = Ticket::query()->create([
                'number' => $this->nextNumber(),
                'customer_id' => $customer->id,
                'virtual_machine_id' => $data['virtual_machine_id'] ?? null,
                'ticket_category_id' => $category?->id,
                'support_team_id' => $team?->id,
                'assigned_user_id' => $assignee?->id,
                'created_by_user_id' => $actor instanceof User ? $actor->id : null,
                'created_by_customer_id' => $actor instanceof Customer ? $actor->id : null,
                'subject' => $data['subject'],
                'priority' => $data['priority'] ?? Ticket::PRIORITY_NORMAL,
                'status' => Ticket::STATUS_OPEN,
                'source' => $actor instanceof User ? 'admin' : 'customer',
                'last_customer_reply_at' => $actor instanceof Customer ? now() : null,
                'last_admin_reply_at' => $actor instanceof User ? now() : null,
                'last_activity_at' => now(),
            ]);

            $message = $this->message($ticket, $actor, TicketMessage::TYPE_REPLY, $data['body']);
            $this->storeAttachments($message, $attachments);
            $this->event($ticket, $actor, 'created', ['assignee_id' => $assignee?->id, 'team_id' => $team?->id]);

            return $ticket;
        });

        $this->notifications->ticketCreated($ticket);

        return $ticket->load('customer', 'category', 'supportTeam', 'assignee', 'virtualMachine', 'messages.attachments');
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function reply(Ticket $ticket, Customer|User $actor, string $body, array $attachments = [], bool $internal = false): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $actor, $body, $attachments, $internal): TicketMessage {
            $type = $internal ? TicketMessage::TYPE_INTERNAL : TicketMessage::TYPE_REPLY;
            $message = $this->message($ticket, $actor, $type, $body);
            $this->storeAttachments($message, $attachments);

            $updates = ['last_activity_at' => now()];
            if (! $internal && $actor instanceof Customer) {
                $updates['status'] = Ticket::STATUS_OPEN;
                $updates['last_customer_reply_at'] = now();
            } elseif (! $internal && $actor instanceof User) {
                $updates['status'] = Ticket::STATUS_ANSWERED;
                $updates['last_admin_reply_at'] = now();
            }

            $ticket->forceFill($updates)->save();
            $this->event($ticket, $actor, $internal ? 'internal_note' : 'reply', ['message_id' => $message->id]);

            return $message;
        });

        if (! $internal && $actor instanceof Customer) {
            $this->notifications->customerReply($ticket->refresh());
        } elseif (! $internal && $actor instanceof User) {
            $this->notifications->adminReply($ticket->refresh());
        }

        return $message;
    }

    public function updateAssignment(Ticket $ticket, User $actor, array $data): Ticket
    {
        $category = TicketCategory::query()->with('supportTeam')->find($data['ticket_category_id'] ?? $ticket->ticket_category_id);
        $team = isset($data['support_team_id'])
            ? SupportTeam::query()->find($data['support_team_id'])
            : $this->assignments->teamFor($category);
        $assignee = isset($data['assigned_user_id'])
            ? User::query()->find($data['assigned_user_id'])
            : $this->assignments->autoAssign($category);

        $ticket->forceFill([
            'ticket_category_id' => $category?->id,
            'support_team_id' => $team?->id,
            'assigned_user_id' => $assignee?->id,
            'last_activity_at' => now(),
        ])->save();

        $this->event($ticket, $actor, 'assignment_changed', [
            'category_id' => $category?->id,
            'team_id' => $team?->id,
            'assignee_id' => $assignee?->id,
        ]);

        return $ticket;
    }

    public function updateStatus(Ticket $ticket, User|Customer $actor, string $status): Ticket
    {
        $ticket->forceFill([
            'status' => $status,
            'closed_at' => $status === Ticket::STATUS_CLOSED ? now() : null,
            'last_activity_at' => now(),
        ])->save();

        $this->event($ticket, $actor, 'status_changed', ['status' => $status]);
        if ($actor instanceof User) {
            $this->notifications->statusChanged($ticket);
        }

        return $ticket;
    }

    public function assertVmBelongsToCustomer(?int $virtualMachineId, Customer $customer): ?VirtualMachine
    {
        if (! $virtualMachineId) {
            return null;
        }

        return $customer->virtualMachines()->whereKey($virtualMachineId)->firstOrFail();
    }

    private function nextNumber(): string
    {
        $prefix = 'T'.now()->format('ymd');
        $last = Ticket::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function message(Ticket $ticket, Customer|User $actor, string $type, string $body): TicketMessage
    {
        return $ticket->messages()->create([
            'author_type' => $actor::class,
            'author_id' => $actor->id,
            'type' => $type,
            'body' => $body,
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    private function storeAttachments(TicketMessage $message, array $attachments): void
    {
        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('ticket-attachments/'.$message->ticket_id);
            $message->attachments()->create([
                'disk' => config('filesystems.default', 'local'),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: Storage::size($path),
            ]);
        }
    }

    private function event(Ticket $ticket, Model $actor, string $type, array $payload = []): TicketEvent
    {
        return $ticket->events()->create([
            'actor_type' => $actor::class,
            'actor_id' => $actor->getKey(),
            'type' => $type,
            'payload' => $payload,
        ]);
    }
}
