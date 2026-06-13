@extends('customer.layout')

@section('title', 'Workspaces')
@section('header_title', 'Workspaces')
@section('header_subtitle', 'Workspaces group machines, members and billing ownership. The Workspace owner pays for every machine inside it.')
@section('breadcrumbs')
    <span class="truncate text-slate-700">Workspaces</span>
@endsection

@php($activeNav = 'projects')

@section('content')
    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($projects as $project)
                @php
                    $membership = $project->members->firstWhere('customer_id', $customer->id);
                    $isActive = (int) $activeProject->id === (int) $project->id;
                    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
                @endphp
                <article class="rounded-lg border {{ $isActive ? 'border-[#0069FF] bg-[#F8FBFF]' : 'border-slate-200 bg-white' }} p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wide {{ $isActive ? 'text-[#0069FF]' : 'text-slate-500' }}">{{ $project->is_default ? 'Default Workspace' : 'Workspace' }}</p>
                            <h2 class="mt-1 truncate text-xl font-black text-slate-950">{{ $project->name }}</h2>
                            <p class="mt-2 truncate text-sm font-bold text-slate-500">Owner: {{ $project->owner?->name }}</p>
                        </div>
                        <span class="shrink-0 rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ ucfirst($membership?->role ?? 'member') }}</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="text-lg font-black text-slate-950">{{ number_format($project->virtual_machines_count ?? 0) }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">Machines</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="text-lg font-black text-slate-950">{{ number_format($project->members_count ?? $project->members->count()) }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">Members</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="truncate text-sm font-black {{ $ownerPays ? 'text-[#0069FF]' : 'text-slate-950' }}">{{ $ownerPays ? 'You' : $project->owner?->name }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">Billing</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @unless($isActive)
                            <form method="POST" action="{{ route('customer.projects.switch', [], false) }}">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <button class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-black text-white transition hover:bg-[#0A2A66]">Switch</button>
                            </form>
                        @endunless
                        <a href="{{ route('customer.projects.show', $project, false) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">Open</a>
                        @if($isActive)
                            <span class="rounded-lg bg-[#EBF3FF] px-4 py-2 text-sm font-black text-[#0069FF]">Active</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">Create Workspace</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">Use a Workspace for a product, team or environment. Machines created inside it are billed to the Workspace owner.</p>
            <form method="POST" action="{{ route('customer.projects.store', [], false) }}" class="mt-5 space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm font-black text-slate-700">Workspace name</span>
                    <input name="name" value="{{ old('name') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" placeholder="Production Servers">
                </label>
                <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">Create Workspace</button>
            </form>

            <div class="mt-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] p-4">
                <p class="text-sm font-black text-[#031B4E]">Billing rule</p>
                <p class="mt-2 text-sm leading-7 text-[#031B4E]/80">The Workspace owner pays for all machines and resources inside the Workspace, even when another member creates them.</p>
            </div>
        </aside>
    </section>
@endsection
