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
<div class="space-y-6" x-data="cloudDns({{ \Illuminate\Support\Js::from(['zone' => $zone->name, 'records' => $dnsRecords, 'old' => session()->getOldInput(), 'validation' => implode(' ', $errors->all())]) }})">
    <div x-cloak x-show="notice" x-text="notice" role="status" class="fixed bottom-6 left-6 z-50 max-w-sm rounded-xl bg-slate-900 px-5 py-3 text-sm text-white shadow-lg"></div>
    @include('cloud-dns.messages', ['modalErrors' => $editable && (in_array(old('type'), $types, true) || old('zone_file'))])
    <div class="flex flex-wrap items-center justify-between gap-4"><div><a href="{{ route($prefix.'index', [], false) }}" class="text-sm font-bold text-[#0069FF]">بازگشت به دامنه‌ها</a><h1 class="mt-3 font-mono text-2xl font-black" dir="ltr">{{ $zone->name }}</h1>@if($admin)<p class="mt-2 text-sm text-slate-500">{{ $zone->project->owner?->name }} · {{ $zone->project->name }}</p>@endif</div><div class="flex items-center gap-3">@include('cloud-dns.status')<span class="text-xs text-slate-500">طرح رایگان</span></div></div>
    @unless($zone->verified_at && $zone->delegation_status === 'active')
        @include('cloud-dns.setup')
    @endunless
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
        <div class="text-sm font-bold">{{ $zone->status === 'ready' && $zone->synced_revision === $zone->revision && ! $zone->last_error ? 'آخرین تغییرات در هر دو نیم‌سرور منتشر شده‌اند' : (in_array($zone->status, ['suspended', 'deleting', 'deleted']) ? 'سرویس این دامنه فعال نیست؛ وضعیت دامنه را بررسی کنید' : ($zone->verified_at ? 'تغییرات در انتظار انتشار هستند' : 'رکوردها پس از تأیید مالکیت منتشر می‌شوند')) }}</div>
        <button type="button" @click="window.location.reload()" class="text-xs font-bold text-[#0069FF]">به‌روزرسانی وضعیت</button><span class="text-xs text-slate-500">{{ $zone->synced_at ? 'آخرین انتشار: '.$zone->synced_at->diffForHumans() : 'هنوز منتشر نشده' }}</span>
    </div>
    @if($zone->last_error)<p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">{{ $zone->last_error }}</p>@endif
    @include('cloud-dns.records')
    @if($zone->verified_at && $zone->delegation_status === 'active')
        <details class="rounded-2xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer text-sm font-bold text-slate-600">مالکیت تأیید شده · نیم‌سرورها متصل‌اند · مشاهده تنظیمات اتصال</summary><div class="mt-4">@include('cloud-dns.setup')</div></details>
    @endif
    <section class="rounded-2xl border border-slate-200 p-5"><h2 class="font-black">تاریخچه تغییرات</h2><div class="mt-4 divide-y divide-slate-100">@foreach($changes as $change)<details class="py-3"><summary class="cursor-pointer text-xs leading-7"><span dir="ltr">{{ ['created' => 'افزودن دامنه', 'verified' => 'تأیید مالکیت', 'records.imported' => 'ورود رکوردها', 'records.updated' => 'ذخیره رکوردها', 'records.deleted' => 'حذف رکورد', 'deleted' => 'حذف دامنه', 'suspended' => 'تعلیق سرویس', 'resumed' => 'فعال‌سازی سرویس'][$change->action] ?? 'تغییر تنظیمات دامنه' }}@if($admin) · {{ $change->actor_type }} #{{ $change->actor_id }}@endif</span> · نسخه {{ $change->revision }} · {{ \App\Support\Jalali::format($change->created_at) }}</summary><div class="mt-3 grid gap-3 md:grid-cols-2">@foreach(['before' => 'قبل', 'after' => 'بعد'] as $key => $label)<div class="min-w-0"><p class="text-xs font-bold">{{ $label }}</p><pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-left text-xs" dir="ltr">{{ json_encode($change->$key, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>@endforeach</div></details>@endforeach</div>{{ $changes->links() }}</section>
    @if($canManage)
    <section class="rounded-2xl border border-red-100 p-5"><h2 class="font-black">مدیریت دامنه</h2><div class="mt-4 flex flex-wrap gap-3">@if($admin && $zone->verified_at && ! $zone->delete_requested_at)<form method="POST" action="{{ route($prefix.'retry', $zone, false) }}">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold">همگام‌سازی مجدد</button></form><form method="POST" action="{{ route($prefix.'suspension', $zone, false) }}" onsubmit="return confirm('وضعیت سرویس DNS این دامنه تغییر کند؟')">@csrf<input type="hidden" name="suspended" value="{{ $zone->status === 'suspended' ? '0' : '1' }}"><button class="rounded-lg border border-amber-200 px-4 py-2 text-xs font-bold text-amber-900">{{ $zone->status === 'suspended' ? 'فعال‌سازی مجدد' : 'تعلیق سرویس DNS' }}</button></form>@endif @unless($zone->delete_requested_at)<form method="POST" action="{{ route($prefix.'destroy', $zone, false) }}" onsubmit="return confirm('حذف دامنه از هر دو سرور، سرویس DNS آن را قطع می‌کند. ادامه می‌دهید؟')">@csrf @method('DELETE')<button class="rounded-lg bg-red-50 px-4 py-2 text-xs font-bold text-red-700">حذف دامنه</button></form>@endunless</div></section>
    @endif
</div>
