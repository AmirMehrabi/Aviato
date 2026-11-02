@php
    $prefix = $admin ? 'admin.cloud-dns.' : 'customer.cloud-dns.';
    $editable = $canManage && ! in_array($zone->status, ['suspended', 'deleting', 'deleted']);
@endphp
@php
    $dnsRecords = $zone->recordSets->map(fn ($record) => [
        'id' => $record->id, 'name' => $record->name, 'type' => $record->type,
        'ttl' => $record->ttl, 'records' => $record->records,
        'deleteUrl' => route($prefix.'records.destroy', [$zone, $record->id], false),
    ])->values();
@endphp
<div class="space-y-6" x-data="cloudDns({{ \Illuminate\Support\Js::from(['zone' => $zone->name, 'records' => $dnsRecords, 'old' => session()->getOldInput(), 'validation' => implode(' ', $errors->all()), 'connection' => $zone->connectionState(), 'statusUrl' => route($prefix.'status', $zone, false), 'previewUrl' => route($prefix.'import.preview', $zone, false)]) }})">
    <div x-cloak x-show="notice" x-text="notice" role="status" class="fixed bottom-6 left-6 z-50 max-w-sm rounded-xl bg-slate-900 px-5 py-3 text-sm text-white shadow-lg"></div>
    @include('cloud-dns.messages', ['modalErrors' => $editable && (in_array(old('type'), $types, true) || old('zone_file'))])
    <div class="flex flex-wrap items-center justify-between gap-4"><div><a href="{{ route($prefix.'index', [], false) }}" class="text-sm font-bold text-[#0069FF]">بازگشت به دامنه‌ها</a><h1 class="mt-3 font-mono text-2xl font-black" dir="ltr">{{ $zone->name }}</h1>@if($admin)<p class="mt-2 text-sm text-slate-500">{{ $zone->project->owner?->name }} · {{ $zone->project->name }}</p>@endif</div><div class="flex items-center gap-3"><span class="rounded-full bg-blue-50 px-3 py-2 text-xs font-bold text-blue-900" x-text="connection.label">{{ $zone->connectionState()['label'] }}</span><span class="text-xs text-slate-500">طرح رایگان</span></div></div>
    <nav aria-label="بخش‌های دامنه" class="flex gap-6 border-b border-slate-200 text-sm font-bold">
        <a href="{{ route($prefix.'show', $zone, false) }}" @unless($management) aria-current="page" @endunless class="border-b-2 px-1 pb-3 {{ ! $management ? 'border-[#0069FF] text-[#0069FF]' : 'border-transparent text-slate-500 hover:text-slate-900' }}">رکوردهای DNS</a>
        <a href="{{ route($prefix.'management', $zone, false) }}" @if($management) aria-current="page" @endif class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 {{ $management ? 'border-[#0069FF] text-[#0069FF]' : 'border-transparent text-slate-500 hover:text-slate-900' }}"><x-admin.icon name="settings" class="size-4" />مدیریت دامنه</a>
    </nav>
    @if($management)
        @include('cloud-dns.management')
    @else
        @unless($zone->verified_at)
            @include('cloud-dns.setup')
        @endunless
        @include('cloud-dns.records')
        @if($zone->last_error)<p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">انتشار تغییرات کامل نشد؛ در صورت تداوم مشکل با پشتیبانی تماس بگیرید.</p>@endif
        @if($zone->verified_at)
            <details id="dns-connection-details" class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4">
                <summary class="cursor-pointer text-sm font-bold text-slate-600">نیم‌سرورها و بررسی اتصال دامنه</summary>
                <div class="mt-4">@include('cloud-dns.setup')</div>
            </details>
        @endif
    @endif
</div>
