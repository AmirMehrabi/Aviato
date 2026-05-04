@extends('layouts.admin')

@section('title', 'نمایش مشتری')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#0A3D37] p-6 text-white shadow-xl shadow-[#0A3D37]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 right-1/3 size-64 rounded-full bg-amber-200/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex items-start gap-4">
                <span class="grid size-16 place-items-center rounded-2xl bg-white text-2xl font-black text-[#0A3D37]">{{ mb_substr($customer->name, 0, 1) }}</span>
                <div>
                    <p class="text-sm font-bold text-emerald-50/60">Customer #{{ $customer->id }}</p>
                    <h1 class="mt-1 text-2xl font-black md:text-4xl">{{ $customer->name }}</h1>
                    <p class="mt-3 leading-8 text-emerald-50/75" dir="ltr">{{ $customer->email ?: 'no-email' }} · {{ $customer->phone ?: 'no-phone' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#0A3D37] transition hover:bg-slate-100">ویرایش</a>
                @if($customer->status === 'suspended')
                    <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-emerald-400 px-5 py-3 text-sm font-black text-emerald-950">فعال‌سازی</button></form>
                @else
                    <form method="POST" action="{{ route('admin.customers.suspend', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-red-400 px-5 py-3 text-sm font-black text-red-950">تعلیق مشتری</button></form>
                @endif
            </div>
        </div>
    </div>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'وضعیت حساب', 'value' => $customer->status === 'suspended' ? 'تعلیق شده' : 'فعال', 'tone' => $customer->status === 'suspended' ? 'text-red-600' : 'text-emerald-700'],
            ['label' => 'اعتبار / بدهی', 'value' => number_format($financial['balance']) . ' تومان', 'tone' => $financial['balance'] < 0 ? 'text-red-600' : 'text-emerald-700'],
            ['label' => 'مصرف ماهانه', 'value' => number_format($financial['monthly_spend']) . ' تومان', 'tone' => 'text-slate-950'],
            ['label' => 'صورتحساب باز', 'value' => number_format($financial['unpaid_total']) . ' تومان', 'tone' => $financial['unpaid_total'] > 0 ? 'text-amber-700' : 'text-emerald-700'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 text-2xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </section>

    @if($customer->status === 'suspended')
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-800">
            <p class="font-black">این مشتری تعلیق شده است.</p>
            <p class="mt-2 text-sm leading-7">{{ $customer->suspension_reason ?: 'دلیلی برای تعلیق ثبت نشده است.' }}</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,0.75fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-950">ماشین‌های مجازی مشتری</h2>
                    <p class="mt-1 text-sm text-slate-500">VMهای واقعی متصل به این مشتری و هزینه PAYG آنها.</p>
                </div>
                <span class="rounded-full bg-[#F1F7F5] px-4 py-2 text-sm font-black text-[#105D52]">{{ $virtualMachines->count() }} VM</span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                @forelse($virtualMachines as $vm)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="font-black text-slate-950" dir="ltr">{{ $vm->name }}</a>
                                <p class="mt-1 text-xs text-slate-500">Node: {{ $vm->node ?: '—' }}</p>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-black {{ $vm->status === 'running' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $vm->status === 'running' ? 'running' : 'stopped' }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->cpu_cores }}</span><span class="text-slate-500">vCPU</span></div>
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->ram_gb }} GB</span><span class="text-slate-500">RAM</span></div>
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->disk_gb }} GB</span><span class="text-slate-500">Disk</span></div>
                        </div>
                        <p class="mt-4 text-left text-sm font-black text-[#105D52]">{{ number_format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }} تومان / ماه</p>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 lg:col-span-3">هنوز VM برای این مشتری ثبت نشده است. <a class="font-black text-[#105D52]" href="{{ route('admin.virtual-machines.create', ['customer_id' => $customer->id]) }}">ساخت VM</a></div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">صورتحساب‌ها</h2>
            <p class="mt-1 text-sm text-slate-500">نمونه وضعیت مالی و فاکتورهای مشتری.</p>
            <div class="mt-5 space-y-3">
                @foreach($invoices as $invoice)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-black text-slate-950">{{ $invoice['number'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $invoice['date']->format('Y/m/d') }}</p>
                            </div>
                            <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $invoice['status'] === 'پرداخت شده' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $invoice['status'] }}</span>
                        </div>
                        <p class="mt-3 text-left text-lg font-black text-slate-950">{{ number_format($invoice['amount']) }} تومان</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black text-slate-950">جزئیات حساب</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">ایمیل</p><p class="mt-2 font-black" dir="ltr">{{ $customer->email ?: '—' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">موبایل</p><p class="mt-2 font-black" dir="ltr">{{ $customer->phone ?: '—' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">آخرین تغییر</p><p class="mt-2 font-black">{{ $customer->updated_at?->diffForHumans() }}</p></div>
        </div>
    </section>
</div>
@endsection
