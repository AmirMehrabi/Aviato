<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Notifications\NotificationInboxService;
use App\Services\Tickets\TicketService;
use App\Support\AdminTableSort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly NotificationInboxService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::statuses()))],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::priorities()))],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ticket_category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'attention' => ['nullable', Rule::in(['unread', 'unassigned'])],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:80'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Ticket::query()
            ->with(['customer', 'category', 'supportTeam', 'assignee', 'virtualMachine', 'latestPublicMessage.author'])
            ->withCount([
                'publicMessages as unread_replies_count' => fn ($query) => $query
                    ->where('author_type', Customer::class)
                    ->whereNull('seen_by_admin_at'),
            ])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assigned_user_id'] ?? null, fn ($query, int $userId) => $query->where('assigned_user_id', $userId))
            ->when($filters['ticket_category_id'] ?? null, fn ($query, int $categoryId) => $query->where('ticket_category_id', $categoryId))
            ->when(($filters['attention'] ?? null) === 'unread', fn ($query) => $query->whereHas(
                'publicMessages',
                fn ($query) => $query->where('author_type', Customer::class)->whereNull('seen_by_admin_at'),
            ))
            ->when(($filters['attention'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_user_id'))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        $sort = AdminTableSort::apply($query, $request, 'tickets');
        $tickets = $query->paginate(15)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => Ticket::statuses(),
            'priorities' => Ticket::priorities(),
            'categories' => TicketCategory::query()->orderBy('name')->pluck('name', 'id'),
            'agents' => User::query()->orderBy('name')->pluck('name', 'id'),
            'ticketCounts' => [
                'open' => Ticket::query()->where('status', Ticket::STATUS_OPEN)->count(),
                'unread' => Ticket::query()->whereHas(
                    'publicMessages',
                    fn ($query) => $query->where('author_type', Customer::class)->whereNull('seen_by_admin_at'),
                )->count(),
                'urgent' => Ticket::query()->where('priority', Ticket::PRIORITY_URGENT)->count(),
                'unassigned' => Ticket::query()->whereNull('assigned_user_id')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $customerId = (int) $request->query('customer_id', 0);

        return view('admin.tickets.create', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'selectedCustomer' => $customerId ? Customer::query()->find($customerId) : null,
            'categories' => TicketCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'priorities' => Ticket::priorities(),
            'agents' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'virtual_machine_id' => ['nullable', 'integer', 'exists:virtual_machines,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(Ticket::priorities()))],
            'body' => ['required', 'string', 'min:3'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $vm = $this->tickets->assertVmBelongsToCustomer($data['virtual_machine_id'] ?? null, $customer);
        $data['virtual_machine_id'] = $vm?->id;

        $ticket = $this->tickets->create($customer, $data, $request->user('admin'), $request->file('attachments', []));

        return redirect()->route('admin.tickets.show', $ticket)->with('status', 'تیکت برای مشتری ثبت شد.');
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['customer.virtualMachines' => fn ($query) => $query->notDeleted(), 'category', 'supportTeam', 'assignee', 'virtualMachine', 'messages.author', 'messages.attachments', 'events.actor']);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'statuses' => Ticket::statuses(),
            'priorities' => Ticket::priorities(),
            'categories' => TicketCategory::query()->orderBy('name')->get(),
            'teams' => SupportTeam::query()->where('is_active', true)->orderBy('name')->get(),
            'agents' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3'],
            'internal' => ['nullable', 'boolean'],
            'mode' => ['nullable', Rule::in(['public', 'internal'])],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $isInternal = ($data['mode'] ?? null) === 'internal' || (bool) ($data['internal'] ?? false);

        $this->tickets->reply($ticket, $request->user('admin'), $data['body'], $request->file('attachments', []), $isInternal);

        return back()->with('status', $isInternal ? 'یادداشت داخلی ثبت شد.' : 'پاسخ برای مشتری ارسال شد.');
    }

    public function seen(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->publicMessages()
            ->where('author_type', Customer::class)
            ->whereNull('seen_by_admin_at')
            ->update(['seen_by_admin_at' => now()]);

        $this->notifications->markTicketRead($request->user('admin'), $ticket);

        return response()->json([
            'unread_replies_count' => 0,
            'notification_unread_count' => $request->user('admin')->unreadNotifications()->count(),
        ]);
    }

    public function assignment(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'ticket_category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'support_team_id' => ['nullable', 'integer', 'exists:support_teams,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->tickets->updateAssignment($ticket, $request->user('admin'), $data);

        return back()->with('status', 'مسیر و مسئول تیکت به‌روزرسانی شد.');
    }

    public function status(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Ticket::statuses()))]]);
        $this->tickets->updateStatus($ticket, $request->user('admin'), $data['status']);

        return back()->with('status', 'وضعیت تیکت به‌روزرسانی شد.');
    }
}
