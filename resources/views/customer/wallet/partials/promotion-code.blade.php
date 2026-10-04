<section class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5" aria-labelledby="promotion-label">
    <form method="POST" action="{{ route('customer.gift-cards.redeem', [], false) }}" @submit="submitGift($event)">
        @csrf
        <input type="hidden" name="project_id" value="{{ $activeProject->id }}">
        <label id="promotion-label" for="promotion-code" class="text-sm font-black text-slate-800">کد هدیه یا پاداش (اختیاری)</label>
        <p class="mt-1 text-xs leading-6 text-slate-500">کد را بررسی کنید تا ارزش و شرایط استفاده از آن را ببینید. بررسی کد، آن را مصرف یا رزرو نمی‌کند.</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input id="promotion-code" x-ref="promotionCode" name="code" :value="code" @input="setCode($event.target.value)" @keydown.enter.prevent="checkCode()" dir="ltr" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64" aria-describedby="promotion-feedback" :aria-invalid="Boolean(codeError)" class="h-12 min-h-12 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 font-mono font-bold uppercase outline-none focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10" placeholder="AVT-XXXX-XXXX-XXXX-XXXX">
            <button type="button" @click="checkCode()" :disabled="!hasCode || checking || submitting" class="min-h-12 shrink-0 rounded-xl border border-[#B8D6FF] bg-white px-5 text-sm font-black text-[#0069FF] transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" x-text="checking ? 'در حال بررسی…' : 'بررسی کد'">بررسی کد</button>
            <button type="button" x-show="hasCode" x-cloak @click="removeCode()" :disabled="submitting" class="min-h-12 shrink-0 rounded-xl px-3 text-sm font-bold text-slate-500 hover:bg-slate-100">حذف کد</button>
        </div>
        <div id="promotion-feedback" role="status" aria-live="polite">
            <p x-show="codeError" x-cloak x-text="codeError" class="mt-3 text-sm font-bold leading-7 text-rose-600"></p>
            @error('code')<p x-show="!promotion" class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
            @error('promotion_code')<p x-show="!promotion" class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
            <div x-show="codeChecked" x-cloak class="mt-4 rounded-xl border border-emerald-200 bg-white p-4">
                <p class="text-xs font-black text-emerald-700">کد معتبر است</p>
                <h3 class="mt-2 text-lg font-black text-slate-950" x-text="benefitTitle"></h3>
                <p class="mt-2 text-sm leading-7 text-slate-600" x-text="isInstant ? 'بدون نیاز به پرداخت، این اعتبار به کیف پول فضای کاری افزوده می‌شود.' : 'این هدیه مبلغ پرداخت را کم نمی‌کند؛ پس از پرداخت موفق، جداگانه به موجودی کیف پول اضافه می‌شود.'"></p>
                <p x-show="promotion?.type === 'top_up_percentage'" class="mt-2 text-sm font-bold text-slate-600" x-text="promotion ? `حداقل پرداخت: ${formatRial(promotion.minimum_top_up)} · سقف پاداش: ${formatRial(promotion.maximum_bonus)}` : ''"></p>
                <p x-show="!isInstant && !minimumMet" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-bold text-amber-900" x-text="promotion ? `برای دریافت هدیه، مبلغ پرداخت را به حداقل ${formatRial(promotion.minimum_top_up)} افزایش دهید.` : ''"></p>
                <p class="mt-3 text-xs text-slate-500" x-text="promotion ? `اعتبار کد تا ${promotion.expires_label}` : ''"></p>
                <p x-show="promotion?.terms" class="mt-2 whitespace-pre-line text-xs leading-6 text-slate-500" x-text="promotion?.terms || ''"></p>
            </div>
        </div>
        <button type="submit" x-show="isInstant" x-cloak :disabled="!canRedeem" class="mt-4 min-h-12 w-full rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto" x-text="submitting ? 'در حال افزودن اعتبار…' : `افزودن ${formatRial(promotion?.credit_amount || 0)} اعتبار هدیه`">افزودن اعتبار هدیه</button>
    </form>
</section>
