@extends('layouts.admin')

@section('title', $account->name)

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Synced: {{ $account->synced_at?->toDateTimeString() ?? 'never' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.hetzner-accounts.edit', $account) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Edit</a>
            <form method="POST" action="{{ route('admin.hetzner-accounts.sync', $account) }}">@csrf<button class="rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-black text-white">Sync catalog</button></form>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black">Locations</h2>
            <div class="mt-4 space-y-3">
                @foreach ($account->locations as $location)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div><b>{{ $location->name }}</b><p class="text-xs text-slate-500">{{ $location->remote_name }} / {{ $location->country }}</p></div>
                            <a href="{{ route('admin.infrastructure-locations.edit', $location) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black">Map bundles</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black">Server types</h2>
            <div class="mt-4 max-h-[520px] overflow-auto rounded-xl border border-slate-100">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">CPU/RAM/Disk</th><th class="px-4 py-3">Arch</th></tr></thead>
                    <tbody>
                        @foreach ($account->serverTypes as $type)
                            <tr class="border-t border-slate-100"><td class="px-4 py-3 font-black">{{ $type->name }}</td><td class="px-4 py-3">{{ $type->cpu_cores }} / {{ $type->memory_gb }}GB / {{ $type->disk_gb }}GB</td><td class="px-4 py-3">{{ $type->architecture }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
