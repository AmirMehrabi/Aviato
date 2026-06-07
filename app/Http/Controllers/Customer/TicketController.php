<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\ProjectAccessService;
use App\Services\Tickets\TicketService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::statuses()))],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $tickets = $customer->tickets()
            ->with(['category', 'supportTeam', 'assignee', 'virtualMachine'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%");
                });
            })
            ->latest('last_activity_at')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('customer.tickets.index', array_merge($this->layoutData($request), [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => Ticket::statuses(),
        ]));
    }

    public function create(Request $request): View
    {
        return view('customer.tickets.create', array_merge($this->layoutData($request), [
            'categories' => TicketCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'priorities' => Ticket::priorities(),
            'virtualMachines' => $request->user('customer')->virtualMachines()->notDeleted()->orderBy('name')->get(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate($this->rules());
        $vm = $this->tickets->assertVmBelongsToCustomer($data['virtual_machine_id'] ?? null, $customer);
        $data['virtual_machine_id'] = $vm?->id;

        $ticket = $this->tickets->create($customer, $data, $customer, $request->file('attachments', []));

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('status', 'تیکت شما ثبت شد.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        abort_unless((int) $ticket->customer_id === (int) $request->user('customer')->id, 404);
        $ticket->load(['category', 'supportTeam', 'assignee', 'virtualMachine', 'messages.author', 'messages.attachments', 'events.actor']);

        return view('customer.tickets.show', array_merge($this->layoutData($request), [
            'ticket' => $ticket,
            'statuses' => Ticket::statuses(),
        ]));
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->customer_id === (int) $request->user('customer')->id, 404);
        abort_if($ticket->status === Ticket::STATUS_CLOSED, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $this->tickets->reply($ticket, $request->user('customer'), $data['body'], $request->file('attachments', []));

        return back()->with('status', 'پاسخ شما ثبت شد.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->customer_id === (int) $request->user('customer')->id, 404);
        $this->tickets->updateStatus($ticket, $request->user('customer'), Ticket::STATUS_CLOSED);

        return back()->with('status', 'تیکت بسته شد.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->customer_id === (int) $request->user('customer')->id, 404);
        $this->tickets->updateStatus($ticket, $request->user('customer'), Ticket::STATUS_OPEN);

        return back()->with('status', 'تیکت دوباره باز شد.');
    }

    private function rules(): array
    {
        return [
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'virtual_machine_id' => ['nullable', 'integer', 'exists:virtual_machines,id'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(Ticket::priorities()))],
            'body' => ['required', 'string', 'min:3'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    private function layoutData(Request $request): array
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);

        return [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'invoiceCount' => $customer->invoices()->count(),
        ];
    }
}
