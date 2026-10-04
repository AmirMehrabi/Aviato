    <section
        id="top-up"
        x-data="walletTopUp({
            initialAmount: @js($initialAmount),
            initialGateway: @js($initialGateway),
            presets: @js($topUpPresets),
            focusTopUp: @js(request()->boolean('topup') || request()->boolean('gift_card')),
            initialCode: @js(old('promotion_code', old('code', ''))),
            previewUrl: @js(route('customer.gift-cards.preview', [], false)),
            projectId: @js($activeProject->id),
        })"
        class="rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="max-w-4xl">
            <div class="min-w-0 p-5 sm:p-7 lg:p-9">
                <h2 class="text-xl font-black text-slate-950">افزایش موجودی</h2>
                <p class="mt-2 text-sm leading-7 text-slate-500">اعتبار هدیه را فعال کنید یا مبلغ مورد نیاز را انتخاب و پرداخت کنید.</p>
                @if (! $canTopUp)
                    <div x-show="!isInstant" class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold leading-7 text-amber-900">
                        فقط مالک، مدیر یا نقش مالی فضای کاری می‌تواند موجودی این کیف پول را افزایش دهد.
                    </div>
                @else
                    @include('customer.wallet.partials.promotion-code')
                    @if (empty($availablePaymentGateways))
                        <div x-show="!isInstant" class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold leading-7 text-amber-900">
                            درگاه پرداخت در حال حاضر فعال نیست. برای افزایش موجودی با پشتیبانی تماس بگیرید.
                        </div>
                    @else
                        <form method="POST" action="{{ route('customer.wallet.topups.store', [], false) }}" class="mt-7" x-show="!isInstant" @submit="submitPayment($event)">
                            @csrf
                            <input type="hidden" name="amount_toman" :value="amount">
                            <input type="hidden" name="promotion_code" :value="codeChecked && !isInstant ? checkedCode : ''">
                            <input type="hidden" name="project_id" value="{{ $activeProject->id }}">

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
                                            @if ($loop->last && $recommendedTopUpToman !== null)<span class="absolute -top-2 right-2 rounded-full bg-[#0069FF] px-2 py-0.5 text-[10px] text-white">برآورد ماهانه</span>@endif
                                            {{ \App\Support\PersianDigits::format($amount) }}
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
                                        placeholder="مثلاً ۷۵۰٬۰۰۰"
                                    >
                                    <span class="shrink-0 border-r border-slate-200 pr-4 text-sm font-black text-slate-500">تومان</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
                                    <span class="text-slate-400">حداقل ۲۵۰٬۰۰۰ و حداکثر ۵۰٬۰۰۰٬۰۰۰ تومان</span>
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

                            <div class="mt-7 flex flex-col gap-4 rounded-2xl border border-[#9FC8FF] bg-[#F2F8FF] p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
                                <div>
                                    <p class="text-xs font-black text-[#31527F]">مبلغ پرداخت شما</p>
                                    <p class="mt-1 text-2xl font-black text-slate-950" x-text="amountLabel"></p>
                                    <dl x-show="codeChecked && !isInstant && amount" x-cloak class="mt-4 space-y-2 text-sm" aria-live="polite">
                                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1"><dt class="text-slate-600">پاداش هدیه</dt><dd class="font-black text-emerald-700" x-text="`+ ${formatRial(bonusAmount)}`"></dd></div>
                                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 border-t border-blue-200 pt-2"><dt class="font-bold text-slate-800">مجموع افزایش موجودی</dt><dd class="font-black text-slate-950" x-text="formatRial(Number(amount) * 10 + bonusAmount)"></dd></div>
                                    </dl>
                                    <p x-show="codeChecked && !isInstant" x-cloak class="mt-3 max-w-md text-xs leading-6 text-slate-500">اعتبار و هدیه پس از تأیید پرداخت اضافه می‌شوند. کد هنگام شروع پرداخت برای {{ config('promotions.reservation_minutes') }} دقیقه رزرو می‌شود.</p>
                                </div>
                                <button
                                    type="submit"
                                    :disabled="!canSubmit"
                                    class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#0069FF] px-7 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                                >
                                    <span x-text="submitLabel">پرداخت و افزایش موجودی</span>
                                </button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>

        </div>
    </section>
