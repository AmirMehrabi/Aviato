<section id="dns-records" class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4 p-5">
        <div><h2 class="flex flex-wrap items-center gap-2 text-lg font-bold"><span>رکوردهای <bdi>DNS</bdi></span> <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-normal text-slate-600">{{ $zone->recordSets->sum(fn ($r) => count($r->records)) }} رکورد</span></h2><p class="mt-2 text-xs text-slate-500">وب‌سایت، ایمیل و سرویس‌های متصل به دامنه</p></div>
        <div class="flex items-center gap-2">
            @if($editable)<button type="button" @click="openEditor()" class="rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white">+ افزودن رکورد</button>@endif
            <details class="relative" @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false">
                <summary class="cursor-pointer rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-600">ابزارها</summary>
                <div class="absolute left-0 top-full z-20 mt-2 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                    @if($editable)<button type="button" @click="$refs.importer.showModal(); $el.closest('details').open = false" class="block w-full rounded px-3 py-2 text-right text-sm hover:bg-slate-50">ورود از فایل</button>@endif
                    <a href="{{ route($prefix.'export', $zone, false) }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-50">خروجی Zone</a>
                </div>
            </details>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-3 border-y border-slate-100 bg-slate-50/70 px-5 py-3">
        <label class="w-full min-w-0 sm:flex-1"><span class="sr-only">جستجوی نام، نوع یا مقدار رکورد</span><input x-model="search" @input.capture="parkEditor()" type="search" placeholder="جستجوی نام، نوع یا مقدار رکورد…" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm"></label>
        <label><span class="sr-only">فیلتر نوع رکورد</span><select x-model="filter" @change.capture="parkEditor()" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"><option value="">همه نوع‌ها</option>@foreach($types as $type)<option>{{ $type }}</option>@endforeach</select></label>
        <span class="text-xs text-slate-500" x-text="filtered.length + ' مجموعه'" aria-live="polite"></span>
    </div>
    @if($editable)<div x-ref="editorTop" class="hidden sm:block"></div>@endif
    <div class="hidden overflow-x-auto sm:block"><table dir="rtl" class="w-full min-w-[720px] text-right text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600"><tr>
            <th class="w-28 px-5 py-3" :aria-sort="sort === 'type' ? (ascending ? 'ascending' : 'descending') : 'none'"><button type="button" @click="sortBy('type')">نوع <span x-text="sort === 'type' ? (ascending ? '↑' : '↓') : '↕'"></span></button></th>
            <th class="w-48 px-5 py-3" :aria-sort="sort === 'name' ? (ascending ? 'ascending' : 'descending') : 'none'"><button type="button" @click="sortBy('name')">نام <span x-text="sort === 'name' ? (ascending ? '↑' : '↓') : '↕'"></span></button></th>
            <th class="px-5 py-3">محتوا / مقصد</th><th class="w-28 px-5 py-3">TTL</th><th class="w-36 px-5 py-3 text-right">مدیریت</th>
        </tr></thead>
        <template x-for="record in filtered" :key="record.id"><tbody class="border-b border-slate-100"><tr class="hover:bg-slate-50" :class="editorOpen && editingRecord?.id === record.id ? 'bg-blue-50/40' : ''">
            <td class="px-5 py-3 align-middle"><span class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs font-medium text-slate-600" x-text="record.type"></span></td>
            <td class="px-5 py-3 align-middle"><button type="button" @click="copy(record.name)" dir="ltr" class="break-all font-mono text-sm font-medium text-slate-900" :title="'کپی ' + record.name" x-text="shortName(record.name)"></button><span class="mt-1 block text-xs text-slate-400" x-show="shortName(record.name) === '@'">ریشه دامنه</span></td>
            <td class="max-w-xl px-5 py-3 align-middle"><template x-for="(value, index) in record.records" :key="index"><div class="flex items-center gap-2 py-1" dir="ltr"><code class="min-w-0 flex-1 whitespace-pre-wrap break-all text-left text-sm leading-6 text-slate-950" x-text="value"></code><button type="button" @click="copy(value)" :aria-label="'کپی مقدار ' + record.type" title="کپی مقدار" class="shrink-0 rounded px-2 py-1 text-xs text-slate-400 hover:text-blue-700">کپی</button></div></template></td>
            <td class="whitespace-nowrap px-5 py-3 align-middle text-sm font-medium text-slate-600" dir="rtl"><span :title="record.ttl + ' ثانیه'" x-text="ttlLabel(record.ttl)"></span></td>
            <td class="px-5 py-3 align-middle">@include('cloud-dns.record-actions')</td>
        </tr>
            @if($editable)<tr x-cloak x-show="editorOpen && editingRecord?.id === record.id"><td colspan="5" class="p-0" :id="'dns-editor-host-' + record.id"></td></tr>@endif
        </tbody></template>
    </table></div>
    <div class="divide-y divide-slate-100 sm:hidden"><template x-for="record in filtered" :key="record.id"><article class="p-4">
        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><code class="block break-all text-left text-base font-bold" dir="ltr" x-text="shortName(record.name)"></code><span class="text-xs text-slate-400" x-show="shortName(record.name) === '@'">ریشه دامنه</span></div><span class="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs text-slate-600" x-text="record.type"></span></div>
        <template x-for="(value, index) in record.records" :key="index"><div class="mt-3 flex items-start gap-2 rounded-lg bg-slate-50 p-3" dir="ltr"><code class="min-w-0 flex-1 whitespace-pre-wrap break-all text-left text-sm leading-6 text-slate-950" x-text="value"></code><button type="button" @click="copy(value)" :aria-label="'کپی مقدار ' + record.type" class="shrink-0 rounded px-2 py-1 text-xs text-slate-400">کپی</button></div></template>
        <div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs text-slate-500">TTL: <span x-text="ttlLabel(record.ttl)"></span></span>@include('cloud-dns.record-actions')</div>
    </article></template></div>
    <div x-show="!filtered.length" class="px-6 py-12 text-center"><h3 class="font-bold" x-text="records.length ? 'رکوردی با این جستجو پیدا نشد' : 'اولین رکورد دامنه را اضافه کنید'"></h3><p class="mt-3 text-sm text-slate-500" x-text="records.length ? 'عبارت جستجو یا فیلتر نوع را تغییر دهید.' : 'از فایل Cloudflare وارد کنید یا برای اتصال وب‌سایت یک رکورد A بسازید.'"></p><button x-show="records.length" type="button" @click="search = ''; filter = ''" class="mt-4 text-sm font-bold text-[#0069FF]">پاک کردن فیلترها</button></div>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-xs leading-6 text-slate-500"><span>SOA و نیم‌سرورهای اصلی به‌صورت خودکار مدیریت می‌شوند.</span><span>سقف طرح: {{ config('cloud-dns.plans.'.$zone->plan.'.record_limit') }} رکورد</span></div>
</section>
@if($editable)
<div x-ref="editorHome">@include('cloud-dns.record-editor')</div>
<dialog x-ref="deleter" aria-labelledby="dns-delete-title" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl backdrop:bg-slate-900/40">
    <h2 id="dns-delete-title" class="text-lg font-black">این رکورد حذف شود؟</h2><p class="mt-3 break-all font-mono text-sm" dir="ltr" x-text="deleting ? deleting.type + ' · ' + deleting.name : ''"></p><p class="mt-3 text-sm leading-7 text-slate-600">همه مقدارهای این ردیف حذف می‌شوند. حذف رکورد می‌تواند دسترسی وب‌سایت یا ایمیل را قطع کند.</p>
    <p x-show="deleting?.type === 'NS'" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm leading-7 text-amber-900">همه نیم‌سرورهای این زیردامنه حذف می‌شوند و واگذاری آن به سرویس مقصد متوقف خواهد شد.</p>
    <form method="POST" :action="deleting?.deleteUrl" class="mt-5 flex justify-end gap-3">@csrf @method('DELETE')<button type="button" @click="$refs.deleter.close()" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold">انصراف</button><button class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white">حذف رکورد</button></form>
</dialog>
<dialog x-ref="importer" aria-labelledby="dns-import-title" class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl overflow-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-xl backdrop:bg-slate-900/40">
    <div class="flex items-center justify-between"><h2 id="dns-import-title" class="text-lg font-black">انتقال رکوردها از سرویس قبلی</h2><button type="button" @click="$refs.importer.close()" aria-label="بستن" class="px-3 py-2 text-xl">×</button></div><p class="mt-3 text-sm leading-7 text-slate-600">در Cloudflare از بخش DNS → Records خروجی Zone بگیرید و متن فایل را اینجا وارد کنید. رکوردهای وب‌سایت و ایمیل را پس از ورود مرور کنید.</p><p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs leading-7 text-amber-900">رکوردهای معتبر به مقدارهای فعلی اضافه می‌شوند؛ مقدارهای تکراری نادیده گرفته می‌شوند و TTL فعلی حفظ می‌شود. موارد نامعتبر جداگانه گزارش می‌شوند. SOA و NS ریشه توسط سامانه مدیریت می‌شوند. تنظیمات پروکسی Cloudflare منتقل نمی‌شوند.</p>
    @if($errors->any() && old('zone_file'))<div role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ implode(' ', $errors->all()) }}</div>@endif
    <form method="POST" action="{{ route($prefix.'import', $zone, false) }}" class="mt-4 space-y-4" @submit="if (!importPreview || previewBusy || fileError) $event.preventDefault()">@csrf<input type="hidden" x-ref="importToken" value="{{ csrf_token() }}">
        <label class="block text-xs font-bold">انتخاب فایل (اختیاری)<input type="file" accept=".txt,.zone,.bind,text/plain" class="mt-2 block w-full rounded-xl border border-slate-200 p-3 text-xs" @change="loadZoneFile($event)"></label><p x-show="fileError" x-text="fileError" role="alert" class="text-xs text-red-600"></p><label class="block text-xs font-bold">متن فایل Zone<textarea x-ref="zoneFile" name="zone_file" x-model="importText" @input="importPreview = null" required rows="8" dir="ltr" placeholder="$ORIGIN example.ir." class="mt-2 w-full rounded-xl border border-slate-300 p-3 font-mono text-xs">{{ old('zone_file') }}</textarea></label><p class="text-xs leading-7 text-slate-500">SOA و NS ریشه از فایل وارد نمی‌شوند؛ نیم‌سرورهای اصلی آویاتو حفظ می‌شوند. پیش‌نمایش تغییری در DNS ایجاد نمی‌کند؛ هنگام ورود، اعتبارسنجی دوباره انجام می‌شود.</p>
        <button type="button" @click="previewImport()" :disabled="previewBusy || !importText || !!fileError" class="rounded-xl border border-[#0069FF] px-5 py-3 text-sm font-bold text-[#0069FF] disabled:opacity-50" x-text="previewBusy ? 'در حال بررسی…' : 'پیش‌نمایش تغییرات'">پیش‌نمایش تغییرات</button>
        <p role="alert" x-show="importError" x-text="importError" class="text-sm text-red-700"></p>
        <div x-show="importPreview" class="space-y-3 rounded-xl border border-slate-200 p-4" aria-live="polite">
            <p class="text-sm font-bold" x-text="importPreview ? importPreview.imported + ' مقدار برای افزودن · ' + importPreview.duplicates + ' تکراری' : ''"></p>
            <template x-for="(warning, index) in importPreview?.warnings || []" :key="index"><p class="text-xs leading-7 text-amber-900" x-text="warning"></p></template>
            <div class="max-h-64 space-y-3 overflow-auto"><template x-for="(record, index) in importPreview?.records || []" :key="index"><div class="border-t border-slate-100 pt-2"><p class="break-all font-mono text-xs" dir="ltr" x-text="record.name + ' ' + record.type + ' ' + record.content"></p><p class="mt-1 text-xs" x-text="record.action"></p></div></template></div>
            <button :disabled="!importPreview?.imported || previewBusy || !!fileError" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white disabled:opacity-50">تأیید و ورود رکوردها</button>
        </div></form>
</dialog>
@endif
