<form method="GET" class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-6">
    <input name="q" value="{{ request('q') }}" placeholder="مشتری، شناسه یا مرجع..." class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#0069FF] xl:col-span-2">
    @isset($statusOptions)
        <select name="{{ $statusName ?? 'status' }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#0069FF]">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statusOptions as $value => $label)<option value="{{ $value }}" @selected(request($statusName ?? 'status') === $value)>{{ $label }}</option>@endforeach
        </select>
    @endisset
    @yield('extra_filters')
    <input name="from" value="{{ request('from', \Morilog\Jalali\Jalalian::fromCarbon($from)->format('Y/m/d')) }}" dir="ltr" aria-label="از تاریخ" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#0069FF]">
    <input name="to" value="{{ request('to', \Morilog\Jalali\Jalalian::fromCarbon($to)->format('Y/m/d')) }}" dir="ltr" aria-label="تا تاریخ" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#0069FF]">
    <div class="flex gap-2">
        <button class="flex-1 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">اعمال</button>
        <a href="{{ url()->current() }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-500">پاک‌کردن</a>
    </div>
</form>
