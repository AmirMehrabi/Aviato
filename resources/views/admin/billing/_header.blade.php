<div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <h1 class="text-2xl font-black text-slate-950">{{ $title }}</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">{{ $subtitle }}</p>
    </div>
    @isset($export)
        <a href="{{ route('admin.billing.exports', ['ledger' => $export] + request()->query()) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-blue-200 hover:text-[#0069FF]">خروجی CSV</a>
    @endisset
</div>
