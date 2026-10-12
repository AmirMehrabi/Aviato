<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;

class AdminDashboardActivity
{
    public function snapshot(int $days, User $user): array
    {
        $to = now();
        $from = $to->copy()->subDays($days - 1)->startOfDay();
        $machines = VirtualMachine::query()->notDeleted()->whereBetween('created_at', [$from, $to]);
        $customers = Customer::query()->whereBetween('created_at', [$from, $to]);
        $tickets = Ticket::query()->with(['customer', 'assignee']);

        return [
            'from' => $from,
            'to' => $to,
            'machines_count' => $user->allows('virtual-machines.read') ? (clone $machines)->count() : null,
            'customers_count' => $user->allows('customers.read') ? (clone $customers)->count() : null,
            'machines' => $user->allows('virtual-machines.read') ? $machines->with('customer')->latest('created_at')->latest('id')->limit(5)->get() : null,
            'customers' => $user->allows('customers.read') ? $customers->when($user->allows('virtual-machines.read'), fn ($query) => $query->withCount(['virtualMachines' => fn ($vms) => $vms->notDeleted()]))->latest('created_at')->latest('id')->limit(5)->get() : null,
            'awaiting_count' => $user->allows('tickets.read') ? Ticket::query()->where('status', Ticket::STATUS_OPEN)->count() : null,
            'awaiting_tickets' => $user->allows('tickets.read') ? (clone $tickets)->where('status', Ticket::STATUS_OPEN)
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
                ->orderByRaw('COALESCE(last_customer_reply_at, created_at)')->orderBy('id')->limit(5)->get() : null,
            'recent_tickets' => $user->allows('tickets.read') ? $tickets->whereBetween(DB::raw('COALESCE(last_activity_at, created_at)'), [$from, $to])
                ->orderByRaw('COALESCE(last_activity_at, created_at) DESC')->orderByDesc('id')->limit(5)->get() : null,
        ];
    }
}
