@extends('customer.layout')

@section('title', 'کیف پول')
@section('header_title', 'کیف پول')
@section('header_subtitle', 'افزایش موجودی، مشاهده مانده و پیگیری تراکنش‌های فضای کاری')

@php
    $activeNav = 'wallet';
    $canTopUp = (bool) $canTopUp;
    $initialGateway = (string) old('gateway', $defaultPaymentGateway);
    $initialAmount = (string) old('amount_toman', '');
@endphp

@section('content')
    @if ($paymentNotice)
        <div class="mb-6 rounded-2xl border px-5 py-4 text-sm font-bold leading-7 {{ $paymentNotice['tone'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($paymentNotice['tone'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800') }}">
            {{ $paymentNotice['message'] }}
        </div>
    @endif

    <section
        id="top-up"
        x-data="walletTopUp({
            initialAmount: @js($initialAmount),
            initialGateway: @js($initialGateway),
            presets: @js($topUpPresets),
            focusTopUp: @js(request()->boolean('topup')),
        })"
        class="overflow-hidden rounded-[32px] border border-[#B8D6FF] bg-white shadow-xl shadow-[#0069FF]/10"
    >
        <div class="grid xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 p-5 sm:p-7 lg:p-9">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-slate-950 sm:text-3xl">افزایش موجودی</h2>
                    </div>
                    <span class="inline-flex w-fit items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700">
                        <span class="size-2 rounded-full bg-[#00A67E]"></span>
                        پرداخت امن
                    </span>
                </div>

                @if (! $canTopUp)
                    <div class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold leading-7 text-amber-900">
                        فقط مالک یا نقش مالی فضای کاری می‌تواند موجودی این کیف پول را افزایش دهد.
                    </div>
                @elseif (empty($availablePaymentGateways))
                    <div class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold leading-7 text-amber-900">
                        درگاه پرداخت در حال حاضر فعال نیست. برای افزایش موجودی با پشتیبانی تماس بگیرید.
                    </div>
                @else
                    <form method="POST" action="{{ route('customer.wallet.topups.store', [], false) }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="amount_toman" :value="amount">

                        <fieldset>
                            <div class="flex items-center justify-between gap-3">
                                <legend class="text-sm font-black text-slate-800">مبلغ شارژ</legend>
                                {{-- <span class="text-xs font-bold text-slate-400">تمام مبلغ‌ها به تومان است</span> --}}
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ($topUpPresets as $amount)
                                    <button
                                        type="button"
                                        @click="selectPreset({{ $amount }})"
                                        :class="selectedPreset === {{ $amount }} ? 'border-[#0069FF] bg-[#EBF3FF] text-[#0069FF] shadow-sm shadow-[#0069FF]/10' : 'border-slate-200 bg-white text-slate-700 hover:border-[#B8D6FF] hover:bg-slate-50'"
                                        class="rounded-2xl border px-3 py-4 text-center text-sm font-black transition focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10"
                                    >
                                        {{ number_format($amount) }}
                                        <span class="mt-1 block text-[11px] font-bold opacity-70">تومان</span>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mt-5">
                            <label for="custom-top-up-amount" class="text-sm font-black text-slate-800">مبلغ دلخواه (تومان)</label>
                            <div
                                class="mt-2 flex items-center rounded-2xl border bg-white px-4 transition focus-within:border-[#0069FF] focus-within:ring-4 focus-within:ring-[#0069FF]/10"
                                :class="customAmount ? 'border-[#B8D6FF]' : 'border-slate-200'"
                            >
                                <input
                                    id="custom-top-up-amount"
                                    x-ref="customAmount"
                                    :value="formattedCustomAmount"
                                    @input="enterCustomAmount($event.target.value)"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    dir="ltr"
                                    class="h-14 min-w-0 flex-1 border-0 bg-transparent text-left text-lg font-black text-slate-950 outline-none placeholder:text-slate-300"
                                    placeholder="مثلا 750,000"
                                >
                                <span class="shrink-0 border-r border-slate-200 pr-4 text-sm font-black text-slate-500">تومان</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
                                <span class="text-slate-400">حداقل ۱۰۰٬۰۰۰ و حداکثر ۵۰٬۰۰۰٬۰۰۰ تومان</span>
                                <button x-show="amount" x-cloak type="button" @click="clearAmount()" class="text-slate-500 transition hover:text-rose-600">پاک کردن مبلغ</button>
                            </div>
                            @error('amount_toman')
                                <p class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if (count($availablePaymentGateways) === 1)
                            <input type="hidden" name="gateway" value="{{ array_key_first($availablePaymentGateways) }}">
                        @else
                            <fieldset class="mt-7">
                                <legend class="text-sm font-black text-slate-800">انتخاب درگاه پرداخت</legend>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach ($availablePaymentGateways as $gatewayKey => $label)
                                        <label
                                            class="flex cursor-pointer items-center gap-3 rounded-2xl border p-4 transition"
                                            :class="gateway === @js($gatewayKey) ? 'border-[#0069FF] bg-[#EBF3FF]' : 'border-slate-200 bg-white hover:border-[#B8D6FF]'"
                                        >
                                            <input x-model="gateway" type="radio" name="gateway" value="{{ $gatewayKey }}" class="sr-only">
                                            <span
                                                class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-black"
                                                :class="gateway === @js($gatewayKey) ? 'bg-[#0069FF] text-white' : 'bg-slate-100 text-slate-500'"
                                            >
                                                {{ mb_substr($label, 0, 1) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-black text-slate-950">{{ $label }}</span>
                                                {{-- <span class="mt-1 block text-xs font-bold text-slate-500">بازگشت خودکار پس از پرداخت</span> --}}
                                            </span>
                                            <span
                                                class="mr-auto grid size-5 shrink-0 place-items-center rounded-full border"
                                                :class="gateway === @js($gatewayKey) ? 'border-[#0069FF] bg-[#0069FF]' : 'border-slate-300 bg-white'"
                                            >
                                                <span x-show="gateway === @js($gatewayKey)" class="size-2 rounded-full bg-white"></span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('gateway')
                                    <p class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        @endif

                        <div class="mt-7 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-black text-slate-500">مبلغ شارژ</p>
                                <p class="mt-1 text-2xl font-black text-slate-950" x-text="amountLabel"></p>
                            </div>
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#0069FF] px-7 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                            >
                                پرداخت و افزایش موجودی
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <aside class="flex min-w-0 flex-col justify-between border-t border-[#B8D6FF] bg-[#F4F8FF] p-6 xl:border-r xl:border-t-0 xl:p-8">
                <div>
                    <p class="text-sm font-black text-[#31527F]">موجودی فعلی</p>
                    <p class="mt-3 break-words text-3xl font-black {{ $wallet->balance < 0 ? 'text-rose-600' : 'text-[#031B4E]' }}">
                        {{ $wallets->format($wallet->balance) }}
                    </p>
                    <p class="mt-3 text-sm font-bold leading-7 text-[#61799C]">
                        موجودی این فضای کاری برای پرداخت هزینه ماشین‌ها و خدمات ابری استفاده می‌شود.
                    </p>
                </div>

                <div class="mt-8 space-y-3 border-t border-[#CFE1FA] pt-6 text-sm font-bold text-[#31527F]">
                    <div class="flex items-center justify-between gap-4">
                        <span>وضعیت کیف پول</span>
                        <span class="{{ $wallet->is_locked ? 'text-rose-600' : 'text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل شده' : 'فعال' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span>کارکرد ثبت نشده</span>
                        <span class="text-slate-950">{{ $wallets->format($pendingUsage) }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="mt-6 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['label' => 'شارژهای این ماه', 'value' => $wallets->format($monthlyCredits), 'tone' => 'text-emerald-700', 'hint' => 'مجموع ورودی‌های کیف پول'],
            ['label' => 'کسرهای این ماه', 'value' => $wallets->format($monthlyCharges), 'tone' => 'text-rose-600', 'hint' => 'کارکرد و سایر برداشت‌ها'],
            ['label' => 'کارکرد ثبت نشده', 'value' => $wallets->format($pendingUsage), 'tone' => $pendingUsage > 0 ? 'text-amber-700' : 'text-emerald-700', 'hint' => 'در برداشت بعدی اعمال می‌شود'],
        ] as $card)
            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-2 break-words text-xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
                <p class="mt-2 text-xs font-bold text-slate-400">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 min-w-0 overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">تاریخچه تراکنش‌ها</h2>
                <p class="mt-1 text-sm font-bold text-slate-500">تمام ورودی‌ها و خروجی‌های کیف پول با مانده بعد از تراکنش ثبت می‌شوند.</p>
            </div>
            <div class="flex max-w-full flex-wrap gap-2">
                @foreach (['all' => 'همه', 'credit' => 'شارژ', 'charge' => 'کارکرد', 'refund' => 'بازگشت', 'adjustment' => 'اصلاح', 'debit' => 'برداشت'] as $type => $label)
                    <a href="{{ route('customer.wallet.show', $type === 'all' ? [] : ['type' => $type], false) }}" class="rounded-full px-3 py-2 text-xs font-black transition {{ $selectedType === $type ? 'bg-[#0069FF] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($transactions as $transaction)
                @php
                    $meta = $transaction->metadata ?? [];
                @endphp
                <article class="min-w-0 p-5">
                    <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <p class="min-w-0 break-words text-base font-black text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</p>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black {{ $transaction->amount >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $transaction->type }}</span>
                                @if (($meta['category'] ?? null) === 'payg_usage')
                                    <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-700">PAYG</span>
                                @endif
                            </div>
                            <div class="mt-2 flex min-w-0 flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-slate-500">
                                <span>{{ $transaction->created_at?->format('Y/m/d H:i') }}</span>
                                <span class="break-words">مانده پس از تراکنش: {{ $wallets->format($transaction->balance_after) }}</span>
                                @if (!empty($meta['vm_name']))
                                    <span class="break-all" dir="ltr">ماشین مجازی: {{ $meta['vm_name'] }}</span>
                                @endif
                                @if (!empty($meta['provider_reference']))
                                    <span class="break-all" dir="ltr">Ref: {{ $meta['provider_reference'] }}</span>
                                @endif
                            </div>
                            @if (($meta['category'] ?? null) === 'payg_usage')
                                <p class="mt-3 break-words text-sm leading-7 text-slate-600">از {{ \Carbon\CarbonImmutable::parse($meta['period_start'])->format('Y/m/d H:i') }} تا {{ \Carbon\CarbonImmutable::parse($meta['period_end'])->format('Y/m/d H:i') }} · {{ number_format((float) ($meta['hours'] ?? 0), 2) }} ساعت · نرخ ساعتی {{ number_format((float) ($meta['hourly_rate'] ?? 0), 2) }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-left">
                            <p class="break-words text-lg font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-sm font-bold text-slate-500">هنوز تراکنشی برای این کیف پول ثبت نشده است.</div>
            @endforelse
        </div>

        <div class="overflow-x-auto border-t border-slate-200 px-5 py-4">
            {{ $transactions->links() }}
        </div>
    </section>

    <script>
        function walletTopUp(config) {
            return {
                amount: '',
                customAmount: '',
                selectedPreset: null,
                gateway: config.initialGateway || '',
                init() {
                    const initialAmount = this.normalizeDigits(config.initialAmount || '');

                    if (initialAmount) {
                        this.amount = initialAmount;
                        const numericAmount = Number(initialAmount);

                        if (config.presets.includes(numericAmount)) {
                            this.selectedPreset = numericAmount;
                        } else {
                            this.customAmount = initialAmount;
                        }
                    }

                    if (config.focusTopUp) {
                        this.$nextTick(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                    }
                },
                normalizeDigits(value) {
                    const digits = {
                        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                        '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                        '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
                    };

                    return String(value)
                        .replace(/[۰-۹٠-٩]/g, (digit) => digits[digit])
                        .replace(/[^\d]/g, '')
                        .replace(/^0+/, '');
                },
                selectPreset(value) {
                    this.selectedPreset = value;
                    this.customAmount = '';
                    this.amount = String(value);
                },
                enterCustomAmount(value) {
                    this.selectedPreset = null;
                    this.customAmount = this.normalizeDigits(value);
                    this.amount = this.customAmount;
                    this.$nextTick(() => {
                        this.$refs.customAmount.value = this.formattedCustomAmount;
                    });
                },
                clearAmount() {
                    this.amount = '';
                    this.customAmount = '';
                    this.selectedPreset = null;
                    this.$nextTick(() => this.$refs.customAmount?.focus());
                },
                formatAmount(value) {
                    return value ? new Intl.NumberFormat('en-US').format(Number(value)) : 'مبلغی انتخاب نشده';
                },
                get formattedCustomAmount() {
                    return this.customAmount ? this.formatAmount(this.customAmount) : '';
                },
                get amountLabel() {
                    return this.amount ? `${this.formatAmount(this.amount)} تومان` : 'مبلغی انتخاب نشده';
                },
                get canSubmit() {
                    const amount = Number(this.amount);

                    return amount >= 100000 && amount <= 50000000 && Boolean(this.gateway);
                },
            };
        }
    </script>
@endsection
