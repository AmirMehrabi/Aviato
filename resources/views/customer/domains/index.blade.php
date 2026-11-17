@extends('customer.layout')
@section('primary_actions_in_content', '1')

@section('title', 'دامنه‌ها')
@section('header_title', $showSearch || $registrationRequested ? 'ثبت دامنه جدید' : 'دامنه‌ها')
@section('header_subtitle', 'فضای کاری: '.$activeProject->name)
@php($activeNav = 'domains')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6" x-data="domainSearch({ scrollToResult: true, workspace: @js($activeProject->uuid), autoSearch: @js(request('from') === 'homepage' && request()->filled('domain') && $canSearch), url: @js(route('customer.domains.check', [], false)), domain: @js(old('domain', request('domain', ''))), result: @js($result), error: @js(($lookupError ?? '') ?: $errors->first('domain')) })">
        @php($selected = $showSearch || $registrationRequested ? 'search' : (request('view') === 'orders' ? 'orders' : 'domains'))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
            <nav aria-label="دامنه‌های فضای کاری" class="flex flex-wrap gap-2 text-sm">
                @foreach([
                    'domains' => ['دامنه‌های من', route('customer.domains.index', [], false)],
                    'orders' => ['سفارش‌ها', route('customer.domains.index', ['view' => 'orders'], false)],
                    'search' => ['ثبت دامنه', route('customer.domains.register', [], false)],
                ] as $tab => [$label, $href])
                    @if($tab !== 'search' || $selected === 'search')
                        <a href="{{ $href }}" @if($selected === $tab) aria-current="page" @endif
                            class="rounded-xl px-4 py-2 font-bold {{ $selected === $tab ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
            @if($canSearch && $selected !== 'search' && ($domainCount || $pendingOrders->isNotEmpty() || $selected === 'orders'))
                <a href="{{ route('customer.domains.register', [], false) }}" class="domain-primary">ثبت دامنه جدید</a>
            @endif
        </div>
        @if($selected === 'domains')
            @if($pendingOrders->isNotEmpty())
                <section class="rounded-2xl border border-blue-100 bg-blue-50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black text-blue-950">سفارش‌های در جریان <span class="font-medium">({{ $pendingOrders->count() }})</span></h2>
                        <a href="{{ route('customer.domains.index', ['view' => 'orders'], false) }}" class="text-sm font-bold text-blue-700">همه سفارش‌ها ←</a>
                    </div>
                    @foreach($pendingOrders->take(3) as $pending)
                        <a href="{{ route('customer.domains.orders.show', $pending, false) }}" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 text-sm hover:bg-blue-50">
                            <bdi dir="ltr" class="break-all font-bold">{{ $pending->domain }}</bdi>
                            <span class="text-slate-600">{{ $pending->label() }}</span>
                            <span class="font-bold text-blue-700">{{ (int) $pending->customer_id === (int) $customer->id && in_array($pending->status, ['draft', 'awaiting_payment']) ? 'ادامه سفارش' : 'مشاهده وضعیت' }} ←</span>
                        </a>
                    @endforeach
                </section>
            @endif
            @include('customer.domains.partials.domain-list')
        @elseif($selected === 'orders')
            @include('customer.domains.partials.orders')
        @elseif(!$canSearch)
            <section class="domain-card">
                <h1 class="domain-heading">ثبت دامنه در دسترس نیست</h1>
                <p class="domain-help mt-2">برای بررسی امکان خرید در این فضای کاری با مالک یا پشتیبانی تماس بگیرید.</p>
            </section>
        @endif

        @if($canSearch && $showSearch)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-9">
            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">دامنه دلخواهتان را پیدا کنید</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">آزاد بودن نام، هزینه ثبت و قیمت فعلی تمدید را بررسی کنید.</p>

            <form method="POST" action="{{ route('customer.domains.check', [], false) }}" @submit.prevent="search()" class="mt-7" :aria-busy="loading">
                @csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}">
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
            <section x-show="result && !loading" x-cloak x-ref="result" tabindex="-1" aria-label="نتیجه بررسی دامنه" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-500">نتیجه بررسی</p>
                        <h2 dir="ltr" class="mt-2 break-all text-left text-2xl font-black text-slate-950" x-text="result?.domain"></h2>
                    </div>
                    <span class="rounded-full px-4 py-2 text-sm font-black" :class="result?.available ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="result?.available ? 'آزاد است' : 'آزاد نیست'"></span>
                </div>
                <p x-show="result?.available && !result?.retail" class="mt-4 text-sm leading-7 text-slate-600"
                    x-text="result?.premium === null ? 'قیمت این نام هنوز تأیید نشده است. دوباره بررسی کنید یا با پشتیبانی تماس بگیرید.' : (result?.premium ? 'دامنه ویژه است. برای دریافت قیمت ثبت و تمدید با پشتیبانی تماس بگیرید.' : 'ثبت این پسوند فعلاً برای خرید در دسترس نیست.')"></p>
                <p x-show="result?.preliminary" x-cloak class="domain-notice mt-4">این نام در اطلاعات عمومی ایرنیک ثبت‌شده دیده نشد. ثبت نهایی به شرایط نام و تأیید ایرنیک بستگی دارد.</p>

                <div x-show="result?.retail" x-cloak class="mt-5 rounded-xl bg-slate-50 p-5">
                    <form method="POST" action="{{ route('customer.domains.orders.store', [], false) }}" class="flex flex-wrap items-end justify-between gap-4">
                        @csrf
                        <input type="hidden" name="workspace" value="{{ $activeProject->uuid }}">
                        <input type="hidden" name="domain" :value="result?.domain">
                        <div>
                            <label for="search-period" class="domain-label">مدت ثبت</label>
                            <select name="period" id="search-period" class="domain-input" @change="period = Number($event.target.value)">
                                <template x-for="term in (result?.retail?.periods || [])" :key="term">
                                    <option :value="term" :selected="term === period" x-text="term + ' سال'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-slate-600">جمع ثبت با مالیات</p>
                            <p class="mt-1 text-lg font-black text-slate-950" x-text="selectedPrice?.total"></p>
                            <p class="mt-1 text-xs text-slate-600">تمدید یک‌ساله با قیمت فعلی: <span x-text="selectedPrice?.renewal"></span></p>
                        </div>
                        <button class="domain-primary w-full sm:w-auto">ادامه ثبت</button>
                    </form>
                    <p x-show="selectedPrice?.requires_handle" class="domain-help mt-3"
                        x-text="result?.domain.endsWith('.ir') ? 'برای ثبت به شناسه ایرنیک نیاز دارید.' : 'برای ثبت به شناسه معتبر رجیستری نیاز دارید.'"></p>
                    <p class="domain-help mt-2">آزاد بودن نام در زمان ثبت دوباره بررسی می‌شود.</p>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-bold text-slate-800">همین نام با پسوندهای دیگر</h3>
                        <button type="button" @click="compare()" :disabled="comparing || cooldown > 0" class="domain-secondary disabled:opacity-60"
                            x-text="comparing ? 'در حال بررسی…' : (comparison.length ? 'بررسی دوباره' : 'مقایسه پسوندها')"></button>
                    </div>
                    <div class="mt-3 divide-y divide-slate-100" :aria-busy="comparing" aria-live="polite">
                        <template x-for="candidate in comparison.filter(item => item.domain !== result?.domain)" :key="candidate.domain">
                            <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <bdi dir="ltr" class="break-all font-bold" x-text="candidate.domain"></bdi>
                                <div class="text-slate-600">
                                    <p x-text="candidate.retail ? candidate.retail.total + ' برای ' + candidate.retail.periods[0] + ' سال' : candidate.available === undefined ? 'بررسی ناموفق' : !candidate.available ? 'آزاد نیست' : candidate.premium ? 'دامنه ویژه' : candidate.premium === null ? 'قیمت تأیید نشده' : 'فعلاً قابل خرید نیست'"></p>
                                    <p x-show="candidate.retail" class="mt-1 text-xs" x-text="'تمدید یک‌ساله: ' + (candidate.retail?.renewal || '')"></p>
                                </div>
                                <button type="button" x-show="candidate.retail || candidate.available === undefined" @click="candidate.retail ? selectResult(candidate) : search(candidate.domain)"
                                    class="domain-secondary" x-text="candidate.retail ? 'انتخاب' : 'تلاش دوباره'"></button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </div>
        <noscript>
            @if ($result)
                <section class="rounded-2xl border border-slate-200 p-6">
                    <h2 dir="ltr" class="break-all text-xl font-black">{{ $result['domain'] }}</h2>
                    <p class="mt-3 font-bold">{{ $result['available'] ? 'آزاد است' : 'آزاد نیست' }}</p>
                    @if(!empty($result['retail']))<p class="mt-4 font-bold">{{ $result['retail']['total'] }}</p><p class="domain-help">تمدید یک‌ساله: {{ $result['retail']['renewal'] }}</p><form class="mt-4" method="POST" action="{{ route('customer.domains.orders.store', [], false) }}">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><input type="hidden" name="domain" value="{{ $result['domain'] }}"><input type="hidden" name="period" value="{{ $result['retail']['periods'][0] }}"><button class="domain-primary">انتخاب و ادامه</button></form>@endif

                    <p class="mt-3 text-sm text-slate-500">این بررسی دامنه را رزرو نمی‌کند. قیمت و شرایط در زمان سفارش بررسی می‌شود.</p>
                </section>
            @endif
        </noscript>

        @endif
    </div>
@endsection
