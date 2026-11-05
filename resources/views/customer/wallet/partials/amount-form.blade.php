<form method="POST" action="{{ route('customer.wallet.topups.store', [], false) }}" id="wallet-top-up-payment" x-show="!isInstant" @submit="submitPayment($event)" :aria-busy="submitting.toString()">
    @csrf
    <input type="hidden" name="amount_toman" :value="amount">
    <input type="hidden" name="promotion_code" :value="codeChecked && !isInstant ? checkedCode : ''">
    <input type="hidden" name="project_id" value="{{ $activeProject->id }}">

    <fieldset>
        <legend class="text-base font-semibold text-slate-900">۱. مبلغ شارژ را انتخاب کنید</legend>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-2">
            <p class="text-xs font-bold text-slate-500">مبلغ‌ها به تومان هستند.</p>
            <span x-show="amount && !amountError" x-cloak class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">مبلغ انتخاب شد</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4">
            @foreach ($topUpPresets as $amount)
                <button
                    type="button"
                    @click="selectPreset({{ $amount }})"
                    :aria-pressed="(selectedPreset === {{ $amount }}).toString()"
                    :class="selectedPreset === {{ $amount }} ? 'border-[#0069FF] bg-[#EBF3FF] text-[#0069FF] shadow-sm shadow-[#0069FF]/10' : 'border-slate-200 bg-white text-slate-700 hover:border-[#B8D6FF] hover:bg-slate-50'"
                    class="relative rounded-2xl border px-3 py-4 text-center text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10"
                >
                    @if ($amount === $recommendedTopUpToman)<span class="absolute -top-2 right-2 rounded-full bg-[#0069FF] px-2 py-0.5 text-[10px] text-white">برآورد ماهانه</span>@endif
                    {{ \App\Support\PersianDigits::format($amount) }}
                    <span class="mt-1 block text-[11px] font-bold opacity-70">تومان</span>
                </button>
            @endforeach
        </div>
    </fieldset>

    <div class="mt-6">
        <label for="custom-top-up-amount" class="text-sm font-semibold text-slate-800">یا مبلغ دلخواه خود را وارد کنید</label>
        <div
            class="mt-2 flex items-center rounded-2xl border bg-white px-4 transition focus-within:border-[#0069FF] focus-within:ring-4 focus-within:ring-[#0069FF]/10"
            :class="amountError ? 'border-rose-400' : (customAmount ? 'border-[#B8D6FF]' : 'border-slate-200')"
        >
            <input
                id="custom-top-up-amount"
                x-ref="customAmount"
                :value="formattedCustomAmount"
                @input="enterCustomAmount($event.target.value)"
                inputmode="numeric"
                autocomplete="off"
                aria-describedby="top-up-amount-help top-up-amount-feedback"
                :aria-invalid="Boolean(amountError).toString()"
                dir="ltr"
                class="h-14 min-w-0 flex-1 border-0 bg-transparent text-left text-lg font-semibold text-slate-950 outline-none placeholder:text-slate-300"
                placeholder="مثلاً ۷۵۰٬۰۰۰"
            >
            <span class="shrink-0 border-r border-slate-200 pr-4 text-sm font-semibold text-slate-500">تومان</span>
        </div>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
            <span id="top-up-amount-help" class="text-slate-500">حداقل {{ \App\Support\PersianDigits::format($minimumTopUpToman) }} و حداکثر ۵۰٬۰۰۰٬۰۰۰ تومان</span>
            <button x-show="amount" x-cloak type="button" @click="clearAmount()" class="text-slate-500 transition hover:text-rose-600">پاک کردن مبلغ</button>
        </div>
        <div id="top-up-amount-feedback" aria-live="polite">
            <p x-show="amountError" x-cloak x-text="amountError" class="mt-2 text-sm font-bold text-rose-600"></p>
            @error('amount_toman')
                <p x-show="!amount" class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if (count($availablePaymentGateways) === 1)
        <input type="hidden" name="gateway" value="{{ array_key_first($availablePaymentGateways) }}">
    @else
        <fieldset class="mt-7 border-t border-slate-100 pt-6">
            <legend class="text-base font-semibold text-slate-900">۲. درگاه پرداخت را انتخاب کنید</legend>
            <p class="mt-1 text-xs font-bold text-slate-500">پس از کلیک، به صفحه امن درگاه منتقل می‌شوید.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($availablePaymentGateways as $gatewayKey => $label)
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-2xl border p-4 transition"
                        :class="gateway === @js($gatewayKey) ? 'border-[#0069FF] bg-[#EBF3FF]' : 'border-slate-200 bg-white hover:border-[#B8D6FF]'"
                    >
                        <input x-model="gateway" type="radio" name="gateway" value="{{ $gatewayKey }}" class="peer sr-only">
                        <span
                            class="grid size-10 shrink-0 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600 peer-focus-visible:ring-offset-2 place-items-center rounded-xl text-sm font-semibold"
                            :class="gateway === @js($gatewayKey) ? 'bg-[#0069FF] text-white' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ mb_substr($label, 0, 1) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-950">{{ $label }}</span>
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

</form>
