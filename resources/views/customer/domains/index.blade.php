@extends('customer.layout')
@section('primary_actions_in_content', '1')

@section('title', 'دامنه‌ها')
@section('header_title', 'دامنه‌ها')
@section('header_subtitle', 'اولین قدم برای ساختن خانه آنلاین شما')
@php($activeNav = 'domains')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6" x-data="domainSearch({ url: @js(route('customer.domains.check', [], false)), domain: @js(old('domain', request('domain', ''))), result: @js($result), error: @js(($lookupError ?? '') ?: $errors->first('domain')) })">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 pb-4 text-sm">
            <span class="rounded-xl bg-blue-50 px-4 py-2 font-black text-[#0069FF]" aria-current="page">جستجوی دامنه</span>
            <a href="#my-domains" class="px-2 text-slate-600">دامنه‌های من</a><a href="#domain-orders" class="px-2 text-slate-600">سفارش‌ها</a>
        </div>

        @if($canSearch)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-9">
            <div class="mb-6 flex size-12 items-center justify-center rounded-2xl bg-blue-50 text-[#0069FF]" aria-hidden="true">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18Z"/></svg>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">نام بعدی شما در اینترنت چیست؟</h1>
            <p class="mt-3 text-sm leading-7 text-slate-500">دامنه دلخواهتان را جستجو کنید و در چند لحظه از آزاد بودن آن مطمئن شوید.</p>

            <form method="POST" action="{{ route('customer.domains.check', [], false) }}" @submit.prevent="search()" class="mt-7" :aria-busy="loading">
                @csrf
                <label for="domain-name" class="mb-2 block text-sm font-bold text-slate-700">نام دامنه</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="domain-name" name="domain" x-ref="domain" x-model="domain" value="{{ old('domain', request('domain', '')) }}" type="text" dir="ltr" inputmode="url" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="2048" required placeholder="example.com" aria-describedby="domain-help domain-error" :aria-invalid="error ? 'true' : 'false'" :readonly="loading" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-4 text-lg font-medium text-slate-950 placeholder:text-slate-400 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <button type="submit" :disabled="loading" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-7 py-4 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-wait disabled:opacity-60">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="42 16"/></svg>
                        <span x-text="loading ? 'در حال بررسی…' : 'بررسی دامنه'">بررسی دامنه</span>
                    </button>
                </div>
                <p id="domain-help" class="mt-3 text-xs leading-6 text-slate-500">نام کامل دامنه یا آدرس سایت را وارد کنید. برای نام بدون پسوند، ‎.com بررسی می‌شود.</p>
                <p id="domain-error" role="alert" x-show="error" x-cloak x-text="error" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-800"></p>
                <noscript>
                    @if (($lookupError ?? '') ?: $errors->first('domain'))<p class="mt-4 text-sm text-red-700">{{ ($lookupError ?? '') ?: $errors->first('domain') }}</p>@endif
                </noscript>
            </form>
        </section>

        <div aria-live="polite" aria-atomic="true">
            <div x-show="loading" x-cloak role="status" class="rounded-2xl border border-blue-100 bg-blue-50 p-6 text-sm font-bold leading-7 text-blue-800">در حال بررسی دامنه شما… این کار ممکن است چند ثانیه طول بکشد.</div>
            <section x-show="result && !loading" x-cloak x-ref="result" tabindex="-1" aria-label="نتیجه بررسی دامنه" class="rounded-2xl border border-slate-200 bg-white p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-500">نتیجه بررسی</p>
                        <h2 dir="ltr" class="mt-2 break-all text-left text-2xl font-black text-slate-950" x-text="result?.domain"></h2>
                    </div>
                    <span class="rounded-full px-4 py-2 text-sm font-black" :class="result?.available ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="result?.available ? 'آزاد است' : 'آزاد نیست'"></span>
                </div>
                <p class="mt-5 text-sm leading-7 text-slate-600" x-text="result?.available ? (result?.premium === null ? 'قیمت عادی یا ویژه این نام هنوز قابل تأیید نیست. کمی بعد دوباره بررسی کنید یا با پشتیبانی تماس بگیرید.' : (result?.premium ? 'این دامنه ویژه است. برای بررسی قیمت ثبت و تمدید با پشتیبانی تماس بگیرید.' : (result?.retail ? 'نام شما آماده است. مشخصات مالک و تنظیمات را تکمیل کنید؛ پرداخت در آخرین مرحله انجام می‌شود.' : 'این دامنه آزاد است. فروش این پسوند هنوز فعال نیست.'))) : 'این دامنه در حال حاضر برای ثبت آزاد نیست. می‌توانید پسوند دیگری را بررسی کنید یا نام تازه‌ای جستجو کنید.'"></p>
                <div x-show="result?.available && result?.retail && !result?.premium" x-cloak class="mt-6 rounded-xl bg-slate-50 p-5">
                    <p class="text-lg font-black text-slate-950" x-text="result?.retail?.total"></p><p class="mt-2 text-xs text-slate-500">تمدید یک‌ساله با قیمت فعلی: <span x-text="result?.retail?.renewal"></span> · شامل مالیات اعلام‌شده</p>
                    <form method="POST" action="{{ route('customer.domains.orders.store', [], false) }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf<input type="hidden" name="domain" :value="result?.domain"><label class="text-xs font-bold" for="search-period">دوره ثبت</label><select name="period" id="search-period" class="domain-input w-auto"><template x-for="period in (result?.retail?.periods || [1])" :key="period"><option :value="period" x-text="period + ' سال'"></option></template></select><button class="domain-primary">انتخاب و ادامه ←</button></form>
                    <p class="domain-help mt-3">قیمت بالا برای یک سال است. قیمت دوره انتخابی در مرحله بررسی سفارش نمایش داده می‌شود.</p>
                </div>

                <p x-show="result?.available && result?.premium" class="mt-3 text-xs leading-6 text-amber-800">این دامنه ویژه (Premium) است و قیمت‌گذاری متفاوتی دارد.</p>
                <p x-show="result?.preliminary" x-cloak class="domain-notice mt-4">این نام در اطلاعات عمومی ایرنیک ثبت‌شده دیده نشد. ثبت نهایی به مجاز بودن نام، دسترسی شناسه و تأیید ایرنیک بستگی دارد.</p>
                <p class="mt-3 text-xs leading-6 text-slate-400">این نتیجه دامنه را رزرو نمی‌کند؛ وضعیت آزاد بودن ممکن است تغییر کند.</p>
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-bold text-slate-700">پسوندهای دیگر را هم بررسی کنید</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="name in alternatives" :key="name">
                            <button type="button" @click="search(name)" :disabled="loading" dir="ltr" class="max-w-full break-all rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-[#0069FF] hover:border-blue-300 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600" x-text="name"></button>
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">برای بررسی هر پیشنهاد، روی آن بزنید.</p>
                </div>
            </section>
        </div>
        <noscript>
            @if ($result)
                <section class="rounded-2xl border border-slate-200 p-6">
                    <h2 dir="ltr" class="break-all text-xl font-black">{{ $result['domain'] }}</h2>
                    <p class="mt-3 font-bold">{{ $result['available'] ? 'آزاد است' : 'آزاد نیست' }}</p>
                    @if(!empty($result['retail']))<p class="mt-4 font-bold">{{ $result['retail']['total'] }}</p><p class="domain-help">تمدید یک‌ساله: {{ $result['retail']['renewal'] }}</p><form class="mt-4" method="POST" action="{{ route('customer.domains.orders.store', [], false) }}">@csrf<input type="hidden" name="domain" value="{{ $result['domain'] }}"><input type="hidden" name="period" value="1"><button class="domain-primary">انتخاب و ادامه</button></form>@endif

                    <p class="mt-3 text-sm text-slate-500">این بررسی دامنه را رزرو نمی‌کند. قیمت و شرایط در زمان سفارش بررسی می‌شود.</p>
                </section>
            @endif
        </noscript>

        <aside class="rounded-2xl bg-slate-50 p-5 sm:p-7">
            <h2 class="text-sm font-black text-slate-800">از یک نام تا خانه آنلاین شما</h2>
            <ol class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <li><span class="font-black text-[#0069FF]">۱. پیدا کردن نام</span><p class="mt-1 text-xs leading-6 text-slate-500">آزاد بودن دامنه دلخواهتان را بررسی کنید.</p></li>
                <li><span class="font-bold text-slate-400">۲. ثبت دامنه</span><p class="mt-1 text-xs leading-6 text-slate-500">خرید دامنه در کنار سرویس‌های ابری شما.</p></li>
                <li><span class="font-bold text-slate-400">۳. مدیریت</span><p class="mt-1 text-xs leading-6 text-slate-500">تمدید و تنظیمات دامنه در همین بخش.</p></li>
            </ol>
        </aside>
        @endif
        <section id="my-domains" class="domain-card scroll-mt-6"><h2 class="domain-heading">دامنه‌های من</h2><div class="mt-4 divide-y divide-slate-100">@forelse($ownedDomains as $ownedDomain)<a href="{{ route('customer.domains.show', $ownedDomain, false) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"><span dir="ltr" class="break-all font-black">{{ $ownedDomain->name }}</span><span class="text-slate-500">انقضا: {{ \App\Support\Jalali::format($ownedDomain->expires_at, 'Y/m/d') }}</span><span class="font-bold text-blue-600">مدیریت ←</span></a>@empty<p class="py-5 text-sm leading-7 text-slate-500">هنوز دامنه‌ای در حساب شما ثبت نشده است.{{ $canSearch ? ' با جستجوی نام دلخواهتان شروع کنید.' : '' }}</p>@endforelse</div></section>
        <section id="domain-orders" class="domain-card scroll-mt-6"><h2 class="domain-heading">سفارش‌های اخیر</h2><p class="domain-help mt-2">از همین‌جا خرید ناتمام را ادامه دهید یا نتیجه ثبت و تمدید را ببینید.</p><div class="mt-4 divide-y divide-slate-100">@forelse($domainOrders as $domainOrder)<a href="{{ route('customer.domains.orders.show', $domainOrder, false) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"><span dir="ltr" class="break-all font-bold">{{ $domainOrder->domain }}</span><span class="text-slate-500">{{ $domainOrder->label() }}</span><span class="font-bold text-blue-600">مشاهده ←</span></a>@empty<p class="py-5 text-sm text-slate-500">هنوز سفارشی ثبت نکرده‌اید.</p>@endforelse</div></section>
    </div>
@endsection
