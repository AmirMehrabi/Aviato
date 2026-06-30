@extends('layouts.admin')

@section('title', 'موقعیت‌های زیرساخت')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    <h1 class="text-2xl font-black">موقعیت‌های زیرساخت</h1>
    <p class="mt-1 text-sm text-slate-500">Customer-selectable locations backed by Proxmox servers or Hetzner accounts.</p>
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ($locations as $location)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-lg font-black">{{ $location->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $location->provider }} / {{ $location->remote_name ?: $location->region }}</p></div>
                    <span class="rounded-md px-2 py-1 text-xs font-black {{ $location->is_active && ! $location->maintenance_mode ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $location->is_active && ! $location->maintenance_mode ? 'Sellable' : 'Hidden' }}</span>
                </div>
                <p class="mt-4 text-sm text-slate-600">{{ $location->bundleMappings->where('is_active', true)->count() }} active bundle mapping(s)</p>
                <a href="{{ route('admin.infrastructure-locations.edit', $location) }}" class="mt-4 inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Edit mappings</a>
            </div>
        @endforeach
    </div>
</div>
@endsection
