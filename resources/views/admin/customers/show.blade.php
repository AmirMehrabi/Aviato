@extends('layouts.admin')

@section('title', 'نمایش مشتری')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 right-1/3 size-64 rounded-full bg-amber-200/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex items-start gap-4">
                <span class="grid size-16 place-items-center rounded-2xl bg-white text-2xl font-black text-[#031B4E]">{{ mb_substr($customer->name, 0, 1) }}</span>
                <div>
                    <p class="text-sm font-bold text-white/60">Customer #{{ $customer->id }}</p>
                    <h1 class="mt-1 text-2xl font-black md:text-4xl">{{ $customer->name }}</h1>
                    <p class="mt-3 leading-8 text-white/75" dir="ltr">{{ $customer->email ?: 'no-email' }} · {{ $customer->phone ?: 'no-phone' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E] transition hover:bg-slate-100">ویرایش</a>
                <form method="POST" action="{{ route('admin.customers.sms-notifications.update', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="sms_notifications_enabled" value="{{ $customer->sms_notifications_enabled ? 0 : 1 }}">
                    <button class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E] transition hover:bg-slate-100">
                        {{ $customer->sms_notifications_enabled ? 'غیرفعال‌سازی پیامک' : 'فعال‌سازی پیامک' }}
                    </button>
                </form>
                @if($customer->status === 'suspended')
                    <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-[#B8D6FF] px-5 py-3 text-sm font-black text-[#031B4E]">فعال‌سازی</button></form>
                @else
                    <form method="POST" action="{{ route('admin.customers.suspend', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-red-400 px-5 py-3 text-sm font-black text-red-950">تعلیق مشتری</button></form>
                @endif
            </div>
        </div>
    </div>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'وضعیت حساب', 'value' => $customer->status === 'suspended' ? 'تعلیق شده' : 'فعال', 'tone' => $customer->status === 'suspended' ? 'text-red-600' : 'text-[#0069FF]'],
            ['label' => 'اعلان پیامکی', 'value' => $customer->sms_notifications_enabled ? 'فعال' : 'غیرفعال', 'tone' => $customer->sms_notifications_enabled ? 'text-[#0069FF]' : 'text-slate-500'],
            ['label' => 'موجودی کیف پول', 'value' => $wallets->format($financial['balance']), 'tone' => $financial['balance'] < 0 ? 'text-red-600' : 'text-[#0069FF]'],
            ['label' => 'مصرف ماهانه', 'value' => $wallets->format($financial['monthly_spend']), 'tone' => 'text-slate-950'],
            ['label' => 'مصرف محاسبه نشده', 'value' => $wallets->format($financial['unpaid_total']), 'tone' => $financial['unpaid_total'] > 0 ? 'text-amber-700' : 'text-[#0069FF]'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 text-2xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(420px,1.05fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-950">کیف پول مشتری</h2>
                    <p class="mt-1 text-sm text-slate-500">موجودی از روی Ledger تراکنش‌ها نگهداری می‌شود و مبلغ اولیه همه مشتریان ۰ است.</p>
                </div>
                <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $wallet->is_locked ? 'bg-red-50 text-red-700' : 'bg-[#EBF3FF] text-[#0069FF]' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
            </div>
            <p class="mt-6 text-4xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-[#0069FF]' }}">{{ $wallets->format($wallet->balance) }}</p>
            <form method="POST" action="{{ route('admin.customers.wallet-transactions.store', $customer) }}" class="mt-6 grid gap-3 md:grid-cols-2">
                @csrf
                <select name="type" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                    <option value="credit">افزایش اعتبار</option>
                    <option value="debit">کسر اعتبار</option>
                </select>
                <input name="amount" type="number" min="1" placeholder="مبلغ" class="rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-[#0069FF] focus:outline-none">
                <input name="description" placeholder="توضیح تراکنش" class="rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-[#0069FF] focus:outline-none md:col-span-2">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-3 text-xs font-bold text-slate-600 md:col-span-2">
                    <input type="checkbox" name="allow_negative" value="1" class="size-4 rounded border-slate-300 text-[#0069FF]">
                    اجازه منفی شدن موجودی برای این کسر اعتبار
                </label>
                <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white md:col-span-2">ثبت تراکنش</button>
            </form>
            <form method="POST" action="{{ route('admin.customers.wallet-lock.update', $customer) }}" class="mt-4 rounded-xl border border-dashed border-slate-300 p-4">
                @csrf @method('PATCH')
                <input type="hidden" name="is_locked" value="{{ $wallet->is_locked ? 0 : 1 }}">
                @unless($wallet->is_locked)
                    <input name="lock_reason" placeholder="دلیل قفل کردن کیف پول" class="mb-3 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-[#0069FF] focus:outline-none">
                @endunless
                <button class="rounded-lg {{ $wallet->is_locked ? 'bg-[#0069FF]' : 'bg-red-600' }} px-5 py-3 text-sm font-black text-white">{{ $wallet->is_locked ? 'باز کردن کیف پول' : 'قفل کردن کیف پول' }}</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">آخرین تراکنش‌های کیف پول</h2>
            <div class="mt-5 space-y-3">
                @forelse($walletTransactions as $transaction)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-black text-slate-950">{{ $transaction->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->created_at?->format('Y/m/d H:i') }} · {{ $transaction->createdBy?->name ?: 'system' }}</p>
                            </div>
                            <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $transaction->amount >= 0 ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-red-50 text-red-700' }}">{{ $wallets->format($transaction->amount) }}</span>
                        </div>
                        <p class="mt-3 text-left text-xs font-bold text-slate-500">Balance: {{ $wallets->format($transaction->balance_after) }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">هنوز تراکنشی ثبت نشده است.</div>
                @endforelse
            </div>
        </div>
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
                <span class="rounded-full bg-[#EBF3FF] px-4 py-2 text-sm font-black text-[#0069FF]">{{ $virtualMachines->count() }} VM</span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                @forelse($virtualMachines as $vm)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="font-black text-slate-950" dir="ltr">{{ $vm->name }}</a>
                                <p class="mt-1 text-xs text-slate-500">Node: {{ $vm->node ?: '—' }}</p>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-black {{ $vm->status === 'running' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-200 text-slate-600' }}">{{ $vm->status === 'running' ? 'running' : 'stopped' }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->cpu_cores }}</span><span class="text-slate-500">vCPU</span></div>
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->ram_gb }} GB</span><span class="text-slate-500">RAM</span></div>
                            <div class="rounded-lg bg-white p-2"><span class="block font-black">{{ $vm->disk_gb }} GB</span><span class="text-slate-500">Disk</span></div>
                        </div>
                        <p class="mt-4 text-left text-sm font-black text-[#0069FF]">{{ $wallets->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }} / ماه</p>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 lg:col-span-3">هنوز VM برای این مشتری ثبت نشده است. <a class="font-black text-[#0069FF]" href="{{ route('admin.virtual-machines.create', ['customer_id' => $customer->id]) }}">ساخت VM</a></div>
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
                            <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $invoice['status'] === 'پرداخت شده' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-amber-50 text-amber-700' }}">{{ $invoice['status'] }}</span>
                        </div>
                        <p class="mt-3 text-left text-lg font-black text-slate-950">{{ $wallets->format($invoice['amount']) }}</p>
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
