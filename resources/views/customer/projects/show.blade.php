@extends('customer.layout')

@section('title', $project->name)
@section('header_title', $project->name)
@section('header_subtitle', 'Workspace overview, members, machine access and settings')
@section('breadcrumbs')
    <a href="{{ route('customer.projects.index', [], false) }}" class="transition hover:text-[#0069FF]">Workspaces</a>
    <span>/</span>
    <span class="truncate text-slate-700">{{ $project->name }}</span>
@endsection

@php
    $activeNav = 'projects';
    $canManageMembers = $activeMembership?->canManageMembers() ?? false;
    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
@endphp

@section('content')
    <section class="grid gap-5 lg:grid-cols-4">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Owner</p>
            <p class="mt-2 truncate text-xl font-black text-slate-950">{{ $project->owner?->name }}</p>
            <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Your role</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ ucfirst($activeMembership?->role ?? 'member') }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">{{ $canManageMembers ? 'Can manage members' : 'Limited member controls' }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Members</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->members->count()) }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">People with access</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Machines</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->virtualMachines->count()) }}</p>
            <a href="{{ route('customer.servers.index', [], false) }}" class="mt-1 inline-flex text-sm font-black text-[#0069FF]">Open machines</a>
        </article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Workspace access</h2>
                        <p class="mt-1 text-sm leading-7 text-slate-500">Members can only see resources inside Workspaces where they have a role.</p>
                    </div>
                    <span class="w-fit rounded-lg {{ $ownerPays ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-700' }} px-3 py-2 text-xs font-black">
                        Billing owner: {{ $project->owner?->name }}
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($project->members as $member)
                        <div class="flex flex-col gap-3 rounded-lg border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-black text-slate-950">{{ $member->customer?->name }}</p>
                                <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if($canManageMembers && $member->customer_id !== $project->owner_customer_id)
                                    <form method="POST" action="{{ route('customer.projects.members.update', [$project, $member], false) }}" class="flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black">
                                            @foreach(['admin' => 'Admin', 'member' => 'Member', 'viewer' => 'Viewer', 'billing' => 'Billing'] as $role => $label)
                                                <option value="{{ $role }}" @selected($member->role === $role)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-black text-white">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('customer.projects.members.destroy', [$project, $member], false) }}" onsubmit="return confirm('Remove this member?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-600">Remove</button>
                                    </form>
                                @else
                                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-600">{{ ucfirst($member->role) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">Workspace settings</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">Owner and Admin roles can rename a Workspace. The internal billing owner does not change.</p>
                @if($canManageMembers)
                    <form method="POST" action="{{ route('customer.projects.update', $project, false) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        <input name="name" value="{{ old('name', $project->name) }}" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" aria-label="Workspace name">
                        <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">Rename</button>
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">Only the Workspace Owner or an Admin can rename this Workspace.</p>
                @endif
            </div>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">Invite member</h2>
            @if($canManageMembers)
                <form method="POST" action="{{ route('customer.projects.members.store', $project, false) }}" class="mt-4 space-y-4">
                    @csrf
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">Customer email or phone</span>
                        <input name="identifier" value="{{ old('identifier') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">Role</span>
                        <select name="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                            <option value="admin">Admin</option>
                            <option value="member" selected>Member</option>
                            <option value="viewer">Viewer</option>
                            <option value="billing">Billing</option>
                        </select>
                    </label>
                    <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">Add member</button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">You need the Owner or Admin role to invite members.</p>
            @endif

            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-950">Role guide</p>
                <div class="mt-3 space-y-2 text-xs font-bold leading-6 text-slate-600">
                    <p><span class="font-black text-slate-900">Owner/Admin:</span> members and machines</p>
                    <p><span class="font-black text-slate-900">Member:</span> machines only</p>
                    <p><span class="font-black text-slate-900">Viewer:</span> read-only resources</p>
                    <p><span class="font-black text-slate-900">Billing:</span> invoices and billing</p>
                </div>
            </div>
        </aside>
    </section>
@endsection
