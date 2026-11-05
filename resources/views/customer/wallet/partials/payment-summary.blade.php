<section
    x-show="!isInstant"
    aria-labelledby="wallet-payment-summary-title"
    class="wallet-payment-summary fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white p-4 shadow-xl shadow-slate-950/10 lg:sticky lg:inset-auto lg:top-24 lg:rounded-2xl lg:border lg:p-6 lg:shadow-sm"
>
    <div class="flex items-center justify-between gap-3">
        <h3 id="wallet-payment-summary-title" class="text-sm font-bold text-slate-900 lg:text-base">خلاصه پرداخت</h3>
        <button type="button" @click="summaryOpen = !summaryOpen" :aria-expanded="summaryOpen.toString()" aria-controls="wallet-payment-details" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600 lg:hidden" x-text="summaryOpen ? 'بستن جزئیات' : 'جزئیات پرداخت'"></button>
    </div>
    <div id="wallet-payment-details" :class="summaryOpen ? 'block' : 'hidden lg:block'" class="max-h-[40dvh] overflow-y-auto lg:max-h-none lg:overflow-visible">
        <p class="mt-4 break-words text-sm font-semibold text-slate-800">{{ $activeProject->name }}</p>
        <p class="mt-1 text-xs leading-6 text-slate-500">مبلغ به کیف پول {{ $activeProject->owner?->name }} اضافه می‌شود و در فضاهای کاری مشترک آن قابل استفاده است.</p>
        <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm" aria-live="polite">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">موجودی فعلی</dt><dd class="font-semibold" x-text="formatRial(currentBalanceRial)"></dd></div>
            <div x-show="codeChecked && bonusAmount > 0" x-cloak class="flex justify-between gap-3"><dt class="text-slate-500">پاداش هدیه</dt><dd class="font-semibold text-emerald-700" x-text="`+ ${formatRial(bonusAmount)}`"></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">موجودی پس از شارژ (تخمینی)</dt><dd class="font-bold" x-text="amount && !amountError ? formatRial(projectedBalanceRial) : '—'"></dd></div>
        </dl>
        <p x-show="amount && !amountError && projectedBalanceRial <= 0" x-cloak class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-6 text-amber-900">این مبلغ برای مثبت شدن موجودی کافی نیست. برای رفع محدودیت سرویس‌ها، مبلغ بیشتری انتخاب کنید.</p>
        <p x-show="codeChecked" x-cloak class="mt-4 text-xs leading-6 text-slate-500">مبلغ و هدیه پس از تأیید پرداخت اضافه می‌شوند. شرایط کد هنگام شروع پرداخت دوباره بررسی می‌شود.</p>
    </div>
    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 lg:mt-5 lg:block lg:pt-5">
        <div class="min-w-0">
            <p class="text-xs text-slate-500">مبلغ پرداخت شما</p>
            <p class="mt-1 break-words text-lg font-bold text-slate-950 lg:text-2xl" x-text="amountLabel"></p>
        </div>
        <button
            form="wallet-top-up-payment"
            type="submit"
            :disabled="!canSubmit"
            :aria-busy="submitting.toString()"
            class="inline-flex min-h-12 max-w-[55%] shrink-0 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:bg-slate-300 lg:mt-5 lg:w-full lg:max-w-none"
        >
            <span x-show="submitting" x-cloak class="size-4 shrink-0 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
            <span x-text="submitting ? 'در حال انتقال…' : 'پرداخت و افزایش موجودی'">پرداخت و افزایش موجودی</span>
        </button>
    </div>
    <p x-show="!canSubmit && !submitting" x-text="submitLabel" class="mt-2 text-xs leading-5 text-slate-500" aria-live="polite"></p>
    <p class="mt-3 hidden text-xs leading-6 text-slate-400 lg:block">پرداخت در درگاه امن بانک انجام می‌شود. موجودی تخمینی با مصرف سرویس‌ها تغییر می‌کند.</p>
</section>
