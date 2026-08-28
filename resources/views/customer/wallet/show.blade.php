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
    @if (session('promotion_success'))
        <div class="mb-6 flex flex-col gap-4 rounded-[28px] border border-emerald-200 bg-emerald-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div><p class="text-xs font-black text-emerald-700">هدیه فعال شد</p><h2 class="mt-2 text-2xl font-black text-slate-950">حالا پروژه‌ات را بالا بیاور.</h2><p class="mt-2 text-sm font-bold text-slate-600">پلن، موقعیت و سیستم‌عامل را انتخاب کنید.</p></div>
            <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-6 font-black text-white">ساخت اولین سرور</a>
        </div>
    @endif
    <section id="gift-card" class="mb-6 rounded-[28px] border border-emerald-200 bg-gradient-to-l from-emerald-50 to-white p-5 shadow-sm sm:p-7">
        <div class="grid gap-5 lg:grid-cols-[1fr_420px] lg:items-center">
            <div><p class="text-xs font-black text-emerald-700">کارت هدیه آویاتو</p><h2 class="mt-2 text-2xl font-black text-slate-950">اعتبار هدیه دارید؟</h2><p class="mt-2 text-sm font-bold leading-7 text-slate-500">کد کارت اعتباری را وارد کنید تا مبلغ آن فوراً به کیف پول فضای کاری فعال افزوده شود. کدهای درصدی را پایین‌تر هنگام پرداخت وارد کنید.</p></div>
            <form method="POST" action="{{ route('customer.gift-cards.redeem', [], false) }}" class="rounded-2xl border border-emerald-200 bg-white p-4">
                @csrf
                <label for="gift-credit-code" class="text-sm font-black text-slate-800">کد اعتبار هدیه</label>
                <div class="mt-2 flex gap-2"><input id="gift-credit-code" name="code" dir="ltr" autocomplete="off" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-3 font-mono font-bold uppercase outline-none focus:border-emerald-500" placeholder="AVT-XXXX-XXXX-XXXX-XXXX"><button class="rounded-xl bg-emerald-600 px-5 text-sm font-black text-white">اعمال</button></div>
                @error('code')<p class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
            </form>
        </div>
    </section>
    @if ($paymentNotice)
        <div role="status" class="mb-6 flex flex-col gap-3 rounded-2xl border px-5 py-4 text-sm font-bold leading-7 sm:flex-row sm:items-center sm:justify-between {{ $paymentNotice['tone'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($paymentNotice['tone'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800') }}">
            <span>{{ $paymentNotice['message'] }}</span>
            @if (! empty($paymentNotice['promotion_success']))
                <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-black text-white">ساخت سرور</a>
            @elseif (! empty($paymentNotice['receipt_url']))
                <a href="{{ $paymentNotice['receipt_url'] }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-black text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">مشاهده رسید پرداخت</a>
            @endif
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
        class="overflow-hidden rounded-[32px] border border-[#9FC8FF] bg-white shadow-2xl shadow-[#0069FF]/10"
    >
        <div class="grid xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 p-5 sm:p-7 lg:p-9">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-black text-[#0069FF]">
                            <span class="grid size-6 place-items-center rounded-full bg-[#EBF3FF]">۱</span>
                            <span>شارژ سریع کیف پول</span>
                        </div>
                        <h2 class="mt-3 text-2xl font-black text-slate-950 sm:text-3xl">چقدر می‌خواهید کیف پول را شارژ کنید؟</h2>
                        <p class="mt-2 max-w-2xl text-sm font-bold leading-7 text-slate-500">مبلغ را انتخاب کنید، درگاه پرداخت را مشخص کنید و به‌صورت امن پرداخت کنید. موجودی پس از تأیید پرداخت به کیف پول فضای کاری اضافه می‌شود.</p>
                    </div>
                    <span class="inline-flex w-fit items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700">
                        <span class="size-2 rounded-full bg-[#00A67E]"></span>
                        پرداخت امن
                    </span>
                </div>

                <div class="mt-6 grid gap-2 rounded-2xl border border-[#D7E8FF] bg-[#F8FBFF] p-3 sm:grid-cols-3">
                    <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-2.5 shadow-sm ring-1 ring-[#E5F0FF]"><span class="grid size-7 place-items-center rounded-lg bg-[#0069FF] text-xs font-black text-white">۱</span><span class="text-xs font-black text-slate-700">انتخاب مبلغ</span></div>
                    <div class="flex items-center gap-2 rounded-xl px-3 py-2.5"><span class="grid size-7 place-items-center rounded-lg bg-slate-200 text-xs font-black text-slate-600">۲</span><span class="text-xs font-black text-slate-500">انتخاب درگاه</span></div>
                    <div class="flex items-center gap-2 rounded-xl px-3 py-2.5"><span class="grid size-7 place-items-center rounded-lg bg-slate-200 text-xs font-black text-slate-600">۳</span><span class="text-xs font-black text-slate-500">پرداخت امن</span></div>
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
                    <form method="POST" action="{{ route('customer.wallet.topups.store', [], false) }}" class="mt-7">
                        @csrf
                        <input type="hidden" name="amount_toman" :value="amount">

                        <fieldset>
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <legend class="text-base font-black text-slate-900">۱. مبلغ شارژ را انتخاب کنید</legend>
                                    <p class="mt-1 text-xs font-bold text-slate-500">مبلغ‌ها به تومان هستند.</p>
                                </div>
                                <span x-show="amount" x-cloak class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">مبلغ انتخاب شد</span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($topUpPresets as $amount)
                                    <button
                                        type="button"
                                        @click="selectPreset({{ $amount }})"
                                        :class="selectedPreset === {{ $amount }} ? 'border-[#0069FF] bg-[#EBF3FF] text-[#0069FF] shadow-sm shadow-[#0069FF]/10' : 'border-slate-200 bg-white text-slate-700 hover:border-[#B8D6FF] hover:bg-slate-50'"
                                        class="relative rounded-2xl border px-3 py-4 text-center text-sm font-black transition focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10"
                                    >
                                        @if ($loop->last)<span class="absolute -top-2 right-2 rounded-full bg-[#0069FF] px-2 py-0.5 text-[10px] text-white">پیشنهاد محبوب</span>@endif
                                        {{ number_format($amount) }}
                                        <span class="mt-1 block text-[11px] font-bold opacity-70">تومان</span>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mt-6">
                            <label for="custom-top-up-amount" class="text-sm font-black text-slate-800">یا مبلغ دلخواه خود را وارد کنید</label>
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
                            <fieldset class="mt-7 border-t border-slate-100 pt-6">
                                <legend class="text-base font-black text-slate-900">۲. درگاه پرداخت را انتخاب کنید</legend>
                                <p class="mt-1 text-xs font-bold text-slate-500">پس از کلیک، به صفحه امن درگاه منتقل می‌شوید.</p>
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

                        <div class="mt-6 border-t border-slate-100 pt-6">
                            <label for="promotion-code" class="text-sm font-black text-slate-800">کد تخفیف کارت هدیه (اختیاری)</label>
                            <input id="promotion-code" name="promotion_code" dir="ltr" autocomplete="off" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-mono font-bold uppercase outline-none focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10" placeholder="AVT-XXXX-XXXX-XXXX-XXXX">
                            <p class="mt-2 text-xs font-bold text-slate-500">کد تا تأیید پرداخت برای ۳۰ دقیقه رزرو می‌شود و پاداش جدا از مبلغ پرداختی در تراکنش‌ها نمایش داده خواهد شد.</p>
                            @error('promotion_code')<p class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mt-7 flex flex-col gap-4 rounded-2xl border border-[#9FC8FF] bg-[#F2F8FF] p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
                            <div>
                                <p class="text-xs font-black text-[#31527F]">۳. مبلغ نهایی پرداخت</p>
                                <p class="mt-1 text-2xl font-black text-slate-950" x-text="amountLabel"></p>
                            </div>
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#0069FF] px-7 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                            >
                                <span x-text="canSubmit ? 'پرداخت و افزایش موجودی' : 'ابتدا مبلغ را انتخاب کنید'"></span>
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

    <section
        x-data="walletTransactions({
            type: @js($selectedType),
            html: @js(trim(view('customer.wallet._transactions', ['transactions' => $transactions, 'wallets' => $wallets])->render())),
            hasPages: @js($transactions->hasPages()),
        })"
        class="mt-6 min-w-0 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60"
    >
        <div class="border-b border-slate-200 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">تاریخچه تراکنش‌ها</h2>
                    <p class="mt-1 text-sm font-bold text-slate-500">تمام ورودی‌ها و خروجی‌های کیف پول با مانده بعد از تراکنش ثبت می‌شوند.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <div class="relative">
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="text"
                        x-model="search"
                        x-on:input.debounce.300ms="page = 1; load()"
                        placeholder="جستجو در توضیحات تراکنش..."
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white pr-11 pl-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10"
                    >
                </div>

                <div class="relative">
                    <input
                        type="text"
                        x-model="from"
                        x-on:click.stop="openPicker('from')"
                        placeholder="از تاریخ (مثلا 1403/01/01)"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10 sm:w-40"
                    >
                    <div
                        x-show="pickerTarget === 'from'"
                        x-on:click.outside="closePicker()"
                        x-cloak
                        class="absolute right-0 top-full z-50 mt-1 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-200/60"
                    >
                        <div class="flex items-center justify-between">
                            <button type="button" x-on:click="prevCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>
                            </button>
                            <span class="text-sm font-black text-slate-900" x-text="calendarMonthName + ' ' + pickerYear"></span>
                            <button type="button" x-on:click="nextCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-black text-slate-400">
                            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <template x-for="(week, wi) in calendarWeeks" :key="wi">
                            <div class="mt-1 grid grid-cols-7 gap-1 text-center">
                                <template x-for="(day, di) in week" :key="di">
                                    <div>
                                        <button
                                            type="button"
                                            x-show="day !== null"
                                            x-on:click="selectDate(day)"
                                            :class="Number(from?.split('/')[2]) === day && pickerTarget === 'from' && from?.startsWith(pickerYear+'/'+String(pickerMonth).padStart(2,'0')) ? 'bg-[#0069FF] text-white' : 'text-slate-700 hover:bg-slate-100'"
                                            class="w-full rounded-lg p-1.5 text-xs font-bold transition"
                                            x-text="day"
                                        ></button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        x-model="to"
                        x-on:click.stop="openPicker('to')"
                        placeholder="تا تاریخ (مثلا 1403/01/01)"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10 sm:w-40"
                    >
                    <div
                        x-show="pickerTarget === 'to'"
                        x-on:click.outside="closePicker()"
                        x-cloak
                        class="absolute right-0 top-full z-50 mt-1 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-200/60"
                    >
                        <div class="flex items-center justify-between">
                            <button type="button" x-on:click="prevCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>
                            </button>
                            <span class="text-sm font-black text-slate-900" x-text="calendarMonthName + ' ' + pickerYear"></span>
                            <button type="button" x-on:click="nextCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-black text-slate-400">
                            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <template x-for="(week, wi) in calendarWeeks" :key="wi">
                            <div class="mt-1 grid grid-cols-7 gap-1 text-center">
                                <template x-for="(day, di) in week" :key="di">
                                    <div>
                                        <button
                                            type="button"
                                            x-show="day !== null"
                                            x-on:click="selectDate(day)"
                                            :class="Number(to?.split('/')[2]) === day && pickerTarget === 'to' && to?.startsWith(pickerYear+'/'+String(pickerMonth).padStart(2,'0')) ? 'bg-[#0069FF] text-white' : 'text-slate-700 hover:bg-slate-100'"
                                            class="w-full rounded-lg p-1.5 text-xs font-bold transition"
                                            x-text="day"
                                        ></button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex max-w-full flex-wrap items-center gap-2">
                @foreach (['all' => 'همه', 'credit' => 'شارژ', 'charge' => 'کارکرد', 'refund' => 'بازگشت', 'adjustment' => 'اصلاح', 'debit' => 'برداشت'] as $type => $label)
                    <button
                        type="button"
                        x-on:click="setType('{{ $type }}')"
                        :class="type === '{{ $type }}' ? 'bg-[#0069FF] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="rounded-full px-3 py-2 text-xs font-black transition"
                    >{{ $label }}</button>
                @endforeach
                <button
                    x-show="from || to || search !== '' || type !== 'all'"
                    x-cloak
                    type="button"
                    x-on:click="clearFilters()"
                    class="mr-auto rounded-full px-3 py-2 text-xs font-black text-rose-600 transition hover:bg-rose-50"
                >حذف فیلتر</button>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-b-[28px]">
            <div
                x-show="loading"
                x-cloak
                class="absolute inset-0 z-40 flex items-center justify-center bg-white/70"
            >
                <svg class="size-10 animate-spin text-[#0069FF]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"/>
                </svg>
            </div>

            <div x-html="html" x-on:click="handlePageClick">
                @include('customer.wallet._transactions', ['transactions' => $transactions, 'wallets' => $wallets])
            </div>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const code = sessionStorage.getItem('aviato.gift_card_code');
    const type = sessionStorage.getItem('aviato.gift_card_type');
    if (!code) return;
    const target = document.getElementById(type === 'top_up_percentage' ? 'promotion-code' : 'gift-credit-code');
    if (target) { target.value = code; target.scrollIntoView({ behavior: 'smooth', block: 'center' }); target.focus(); }
    sessionStorage.removeItem('aviato.gift_card_code');
    sessionStorage.removeItem('aviato.gift_card_type');
});
</script>
@endpush
