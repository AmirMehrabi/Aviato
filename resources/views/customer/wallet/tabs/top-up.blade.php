    <section
        id="top-up"
        @pageshow.window="resetSubmission()"
        x-data="walletTopUp({
            initialAmount: @js($initialAmount),
            initialGateway: @js($initialGateway),
            minimumTopUpToman: @js($minimumTopUpToman),
            presets: @js($topUpPresets),
            initialCode: @js(old('promotion_code', old('code', $retryCode))),
            previewUrl: @js(route('customer.gift-cards.preview', [], false)),
            projectId: @js($activeProject->id),
            currentBalanceRial: @js($currentBalanceRial),
            openPromotion: @js(request()->boolean('gift_card') || $errors->has('code') || $errors->has('promotion_code')),
        })"
        class="wallet-top-up scroll-mt-24 pb-48 lg:pb-0"
    >
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-slate-950">افزایش موجودی</h2>
                        <p class="mt-2 text-sm leading-7 text-slate-500">مبلغ را انتخاب کنید، جزئیات را بررسی کنید و به درگاه امن پرداخت بروید.</p>
                    </div>
                    @if (! empty($restoreAmountToman))
                        <p class="mb-6 rounded-xl bg-blue-50 p-4 text-sm leading-7 text-blue-950">مبلغ پیشنهادی برای پوشش بدهی و هزینه مصرف اخیر انتخاب شده است. حداقل مبلغ پرداخت {{ \App\Support\PersianDigits::format($minimumTopUpToman) }} تومان است. محدودیت سرورها پس از تأیید پرداخت و مثبت شدن موجودی قابل استفاده برداشته می‌شود.</p>
                    @endif
                    @if (! $canTopUp)
                        <p class="rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">فقط مالک، مدیر یا نقش مالی فضای کاری می‌تواند موجودی این کیف پول را افزایش دهد.</p>
                    @elseif (empty($availablePaymentGateways))
                        <p class="rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">درگاه پرداخت در حال حاضر فعال نیست. برای افزایش موجودی با پشتیبانی تماس بگیرید.</p>
                    @else
                        <div x-show="!isInstant">
                            @include('customer.wallet.partials.amount-form')
                        </div>
                        <p x-show="isInstant" x-cloak class="rounded-xl bg-emerald-50 p-4 text-sm leading-7 text-emerald-800">این کد بدون پرداخت بانکی اعتبار اضافه می‌کند. برای ادامه، دکمه افزودن اعتبار هدیه را انتخاب کنید.</p>
                    @endif
                </div>

                @if ($canTopUp)
                    @include('customer.wallet.partials.promotion-code')
                @endif
                @if ($returnAction)
                    <a href="{{ $returnAction['url'] }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-medium text-slate-500 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600">{{ $returnAction['label'] }} ←</a>
                @endif
            </div>
            @if ($canTopUp && ! empty($availablePaymentGateways))
                @include('customer.wallet.partials.payment-summary')
            @endif
        </div>
    </section>
