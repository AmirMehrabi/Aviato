@extends('customer.layout')

@section('title', 'کیف پول')
@section('header_title', 'کیف پول و تراکنش ها')
@section('header_subtitle', 'شارژ کیف پول، پیگیری تراکنش ها و مشاهده کارکرد ثبت نشده')

@php($activeNav = 'wallet')

@section('content')
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'موجودی فعلی', 'value' => $wallets->format($wallet->balance), 'tone' => $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950', 'hint' => $wallet->is_locked ? 'کیف پول قفل است' : 'کیف پول فعال است'],
            ['label' => 'شارژهای این ماه', 'value' => $wallets->format($monthlyCredits), 'tone' => 'text-emerald-600', 'hint' => 'مجموع تراکنش های افزایشی'],
            ['label' => 'کسرهای این ماه', 'value' => $wallets->format($monthlyCharges), 'tone' => 'text-rose-600', 'hint' => 'کارکرد و سایر برداشت ها'],
            ['label' => 'کارکرد ثبت نشده', 'value' => $wallets->format($pendingUsage), 'tone' => $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600', 'hint' => 'در شارژ دوره ای بعدی اعمال می شود'],
        ] as $card)
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 text-2xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
                <p class="mt-2 text-xs font-bold text-slate-400">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-slate-950">افزایش اعتبار</h2>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-[#2563EB]">درگاه آزمایشی</span>
                </div>
                <p class="mt-2 text-sm leading-7 text-slate-500">برای این نسخه، پرداخت از طریق درگاه آزمایشی انجام می شود اما ساختار آماده اتصال به درگاه واقعی است.</p>
                <form method="POST" action="{{ route('customer.wallet.topups.store', [], false) }}" class="mt-5 space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($topUpPresets as $preset)
                            <label class="cursor-pointer rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-sm font-black text-slate-700 transition hover:border-blue-300 hover:bg-blue-50">
                                <input type="radio" name="amount" value="{{ $preset }}" class="sr-only" @checked((int) old('amount', request('topup') ? $topUpPresets[1] : 0) === $preset)>
                                {{ number_format($preset) }}
                            </label>
                        @endforeach
                    </div>
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">مبلغ دلخواه</span>
                        <input type="number" name="custom_amount" min="10000" value="{{ old('custom_amount') }}" placeholder="مثلا 750000" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    </label>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl bg-[#2563EB] px-4 py-3 text-sm font-black text-white transition hover:bg-[#1d4ed8]">انتقال به درگاه آزمایشی</button>
                </form>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">راهنمای برداشت ها</h2>
                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600">
                    <p>کسرهای PAYG با نوع <span class="font-black text-rose-600">charge</span> ثبت می شوند و به ازای هر VM توضیح دقیق دارند.</p>
                    <p>شارژهای کیف پول با نوع <span class="font-black text-emerald-600">credit</span> ثبت می شوند و بعدا می توانند به پرداخت های واقعی درگاه متصل شوند.</p>
                    <p>صورتحساب پایان ماه همان برداشت های ثبت شده در کیف پول را به صورت تجمیعی و تفصیلی نمایش می دهد.</p>
                </div>
            </div>
        </aside>

        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">تاریخچه تراکنش ها</h2>
                    <p class="mt-1 text-sm text-slate-500">تمام ورودی ها و خروجی های کیف پول با مانده بعد از تراکنش ثبت می شوند.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => 'همه', 'credit' => 'شارژ', 'charge' => 'کارکرد', 'refund' => 'بازگشت', 'adjustment' => 'اصلاح', 'debit' => 'برداشت'] as $type => $label)
                        <a href="{{ route('customer.wallet.show', $type === 'all' ? [] : ['type' => $type], false) }}" class="rounded-full px-3 py-2 text-xs font-black transition {{ $selectedType === $type ? 'bg-[#2563EB] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($transactions as $transaction)
                    @php($meta = $transaction->metadata ?? [])
                    <article class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-black {{ $transaction->amount >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $transaction->type }}</span>
                                    @if (($meta['category'] ?? null) === 'payg_usage')
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-700">PAYG</span>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-slate-500">
                                    <span>{{ $transaction->created_at?->format('Y/m/d H:i') }}</span>
                                    <span>مانده پس از تراکنش: {{ $wallets->format($transaction->balance_after) }}</span>
                                    @if (!empty($meta['vm_name']))
                                        <span dir="ltr">VM: {{ $meta['vm_name'] }}</span>
                                    @endif
                                    @if (!empty($meta['provider_reference']))
                                        <span dir="ltr">Ref: {{ $meta['provider_reference'] }}</span>
                                    @endif
                                </div>
                                @if (($meta['category'] ?? null) === 'payg_usage')
                                    <p class="mt-3 text-sm leading-7 text-slate-600">از {{ \Carbon\CarbonImmutable::parse($meta['period_start'])->format('Y/m/d H:i') }} تا {{ \Carbon\CarbonImmutable::parse($meta['period_end'])->format('Y/m/d H:i') }} · {{ number_format((float) ($meta['hours'] ?? 0), 2) }} ساعت · نرخ ساعتی {{ number_format((float) ($meta['hourly_rate'] ?? 0), 2) }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-left">
                                <p class="text-lg font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">هنوز تراکنشی برای این کیف پول ثبت نشده است.</div>
                @endforelse
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                {{ $transactions->links() }}
            </div>
        </div>
    </section>
@endsection
