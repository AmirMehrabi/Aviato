@extends('layouts.admin')
@section('title', 'فروش و سفارش‌های دامنه')
@section('content')
<div class="space-y-6 px-4 py-6 md:px-8 lg:px-10">
    <a class="text-sm font-bold text-blue-600" href="{{ route('admin.settings.domains.edit') }}">→ اتصال و دسترسی مشتریان</a>
    <div class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-2xl font-black">فروش و سفارش‌های دامنه</h1><p class="mt-2 text-sm leading-7 text-slate-500">قیمت‌ها را بررسی کنید، فروش را برای پسوندهای منتخب فعال کنید و نتیجه سفارش‌ها را پیگیری کنید.</p></div><a href="{{ route('admin.settings.domains.accounting.export') }}" class="domain-secondary">خروجی حسابداری CSV</a></div>
    @if(session('status'))<p role="status" class="domain-notice">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="domain-error">{{ $errors->first() }}</div>@endif
    @if($manualPriceReviewCount ?? 0)<p class="domain-notice">{{ $manualPriceReviewCount }} قیمت فعال IRPOWER تا هفت روز آینده منقضی می‌شود یا منقضی شده است. هزینه‌های دستی و تاریخ اعتبار را بررسی کنید؛ پس از پایان اعتبار، پیشنهاد از فروش کنار گذاشته می‌شود.</p>@endif
    @if($reviewCount)<p class="domain-notice">{{ $reviewCount }} سفارش نیازمند پیگیری نتیجه با سرویس‌دهنده است. تا تأیید نتیجه، درخواست ثبت یا تمدید را دوباره ارسال نکنید.</p>@endif
    @php($commerceSection = request('section') === 'orders' ? 'orders' : 'pricing')
    <nav aria-label="مدیریت فروش دامنه" class="flex gap-3 border-b border-slate-200 pb-3">
        @foreach(['pricing' => 'پسوندها و قیمت‌گذاری', 'orders' => 'سفارش‌ها و پیگیری'] as $section => $label)
            <a href="{{ request()->fullUrlWithQuery(['section' => $section]) }}" @if($commerceSection === $section) aria-current="page" @endif class="rounded-lg px-4 py-2 text-sm font-bold {{ $commerceSection === $section ? 'bg-blue-50 text-blue-700' : 'text-slate-500' }}">{{ $label }}</a>
        @endforeach
    </nav>
    @if($commerceSection === 'pricing')
    <details class="domain-card"><summary class="domain-heading cursor-pointer">آمادگی فروش و اتصال سرویس‌دهنده‌ها</summary><div class="mt-4 flex flex-wrap gap-3 text-sm"><span class="domain-badge">کلید عمومی خرید: {{ \App\Models\AppSetting::domainSalesEnabled() ? 'روشن' : 'خاموش' }}</span>@foreach($providerLabels as $key=>$label)<span class="domain-badge">{{ $label }} برای ثبت تازه: {{ ($providerReadiness[$key] ?? false) ? 'فعال' : 'خاموش یا بدون اتصال' }}</span>@endforeach</div><p class="domain-help mt-3">برای نمایش پیشنهاد خرید، کلید عمومی، اتصال سرویس‌دهنده، قیمت ثبت و قیمت تمدید همان سرویس‌دهنده باید فعال و معتبر باشند. دسترسی مشتری نیز باید انتخاب شده باشد. <a class="font-bold text-blue-600" href="{{ route('admin.settings.domains.edit') }}">تنظیم اتصال، کلیدها و دسترسی مشتریان</a></p></details>
    <section class="domain-card"><div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="domain-heading">قیمت‌گذاری پسوندها</h2><p class="domain-help mt-2">قیمت‌های تازه با پیشنهاد ۲۵٪ افزایش آماده می‌شوند و تا تأیید شما غیرفعال‌اند. همه مبالغ ورودی تومان‌اند. هزینه سرویس‌دهنده در پایگاه داده ریال است. قیمت فروش بدون مالیات وارد می‌شود.</p></div></div>
        <details class="mt-5 rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-bold">دریافت و افزودن قیمت‌های سرویس‌دهنده</summary><div class="mt-4"><form method="POST" action="{{ route('admin.settings.domains.prices.sync') }}">@csrf<button class="domain-primary">به‌روزرسانی هزینه‌های Reseller.world</button></form></div>        <div class="mt-5 rounded-xl bg-slate-50 p-4 space-y-4"><p class="domain-help">IRPOWER قیمت خودکار ارائه نمی‌کند؛ هزینه‌های دستی باید تا تاریخ اعتبار مشخص تأیید شوند. برای ورود اولیه جدول قیمت اعلام‌شده از گزینه زیر استفاده کنید. هیچ قیمت موجودی بازنویسی نمی‌شود.</p><form method="POST" action="{{ route('admin.settings.domains.irpower.import') }}">@csrf<button class="domain-secondary">افزودن جدول اولیه IRPOWER و ‎.ir</button></form></div>
</details>
        <nav aria-label="فهرست پسوندها" class="mt-6 flex flex-wrap gap-2">
            @foreach(['mine' => 'پسوندهای من', 'attention' => 'نیازمند بررسی', 'all' => 'همه پسوندها'] as $tab => $label)
                <a href="{{ request()->fullUrlWithQuery(['catalog' => $tab, 'prices_page' => null]) }}" @if($catalogTab === $tab) aria-current="page" @endif class="rounded-lg border px-4 py-3 text-sm font-bold {{ $catalogTab === $tab ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600' }}">{{ $label }} ({{ $catalogCounts[$tab] }})</a>
            @endforeach
            <a href="{{ request()->fullUrlWithQuery(['catalog' => 'all', 'extension' => null, 'provider' => null, 'prices_page' => null]) }}#extension" class="domain-secondary">افزودن پسوند</a>
        </nav>
        <div class="mt-5 flex flex-wrap gap-2">@foreach(['com','net','org','ir'] as $quickExtension)<a href="{{ route('admin.settings.domains.commerce', array_merge(request()->except('prices_page'), ['extension'=>$quickExtension, 'catalog'=>'all'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-blue-600" dir="ltr">.{{ $quickExtension }}</a>@endforeach</div>
        <form method="GET" class="mt-5 flex flex-col gap-3 sm:flex-row"><input type="hidden" name="catalog" value="{{ $catalogTab }}">@if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif<label for="extension" class="sr-only">فیلتر پسوند</label><input id="extension" name="extension" value="{{ request('extension') }}" placeholder="جستجوی پسوند، مانند .com" dir="ltr" class="domain-input max-w-xs"><select name="provider" aria-label="سرویس‌دهنده" class="domain-input max-w-xs"><option value="">هر دو سرویس‌دهنده</option>@foreach($providerLabels as $key=>$label)<option value="{{ $key }}" @selected(request('provider') === $key)>{{ $label }}</option>@endforeach</select><button class="domain-secondary">فیلتر قیمت‌ها</button></form>
        <p class="domain-help mt-4">مبالغ فهرست تومان و بدون مالیات هستند. برای ویرایش قیمت ثبت، تمدید و مسیر سرویس‌دهنده، «مدیریت» را باز کنید.</p>
        <div class="mt-6 space-y-3">@forelse($extensions as $entry)
            <details data-domain-editor id="extension-{{ $entry['extension'] }}" class="rounded-xl border border-slate-200 bg-white p-4">
                <summary class="cursor-pointer text-sm">
                    <span class="inline-flex flex-wrap items-center gap-x-6 gap-y-3 align-middle">
                        <strong dir="ltr" class="text-lg">.{{ $entry['extension'] }}</strong>
                        <span class="{{ $entry['ready'] ? 'text-emerald-700' : ($entry['enabled'] ? 'text-amber-700' : 'text-slate-500') }}">{{ $entry['reason'] }}</span>
                        <span>ثبت سال اول: <b>{{ $entry['selected']?->retail_price ? number_format((int) ceil($entry['selected']->retail_price / 10)) : '—' }}</b></span>
                        <span>تمدید سالانه: <b>{{ $entry['renewal']?->retail_price ? number_format((int) ceil($entry['renewal']->retail_price / 10)) : '—' }}</b></span>
                        <span class="text-slate-500">{{ $entry['selected'] ? $providerLabels[$entry['selected']->provider] : 'بدون پیشنهاد ثبت' }}</span>
                        <span class="text-xs text-slate-500">مسیر: {{ ['cheapest' => 'کمترین قیمت ثبت', 'two_year' => 'کمترین هزینه دو سال', 'reseller_world' => 'فقط Reseller.world', 'irpower' => 'فقط IRPOWER'][$entry['policy']] ?? $entry['policy'] }}</span>
                        <span class="font-bold text-blue-600">مدیریت</span>
                    </span>
                </summary>
                        <div class="mt-5 rounded-xl border border-blue-100 p-4"><h3 class="font-bold">انتخاب سرویس‌دهنده برای یک پسوند</h3><form method="POST" action="{{ route('admin.settings.domains.routing') }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf<label><span class="domain-label">پسوند، بدون نقطه اول</span><input name="extension" required dir="ltr" value="{{ $entry['extension'] }}" placeholder="com یا ir" class="domain-input"></label><label><span class="domain-label">روش انتخاب</span><select name="policy" class="domain-input">@foreach(['default'=>'پیروی از تنظیم عمومی', 'cheapest'=>'کمترین قیمت فروش سال اول', 'two_year'=>'کمترین هزینه ثبت و تمدید سال بعد', 'reseller_world'=>'فقط Reseller.world', 'irpower'=>'فقط IRPOWER'] as $policy=>$label)<option value="{{ $policy }}" @selected(($providerRoutes[$entry['extension']] ?? 'default') === $policy)>{{ $label }}</option>@endforeach</select></label><button class="domain-secondary self-end">ذخیره مسیر پسوند</button></form><p class="domain-help mt-3">انتخاب فقط بین پیشنهادهای فعال با قیمت ثبت و تمدید معتبر انجام می‌شود. تمدیدها و سفارش‌های قبلی به سرویس‌دهنده اصلی متصل می‌مانند.</p></div>

                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                @foreach($entry['products']->groupBy('provider') as $provider => $providerProducts)
                    <div class="space-y-3"><h3 class="font-bold text-blue-700">{{ $providerLabels[$provider] }}</h3>
                    @foreach($providerProducts->sortBy(fn ($item) => sprintf('%02d-%s', $item->period, $item->operation)) as $product)
                        <details class="rounded-xl border border-slate-100 p-3" @if($product->period === 1) open @endif><summary class="cursor-pointer text-sm font-bold">{{ $product->operation === 'register' ? 'ثبت' : 'تمدید' }} · {{ $product->period }} سال · {{ $product->enabled ? 'فعال' : 'غیرفعال' }}</summary>
                            @include('admin.settings.partials.domain-product-form', ['product' => $product])
                        </details>
                    @endforeach
                    </div>
                @endforeach
                </div>
            </details>
        @empty
            <div class="rounded-xl bg-slate-50 p-8 text-center"><p class="text-sm text-slate-600">{{ $catalogTab === 'mine' ? 'هنوز پسوند فعالی در این فهرست ندارید. از همه پسوندها، ثبت و تمدید را بررسی و فعال کنید.' : ($catalogTab === 'attention' ? 'پسوند فعالی نیازمند بررسی نیست.' : 'پسوندی با این فیلتر پیدا نشد. فیلتر را تغییر دهید یا قیمت‌های سرویس‌دهنده را دریافت کنید.') }}</p><a href="{{ request()->fullUrlWithQuery(['catalog' => 'all', 'extension' => null, 'provider' => null, 'prices_page' => null]) }}" class="mt-4 inline-block font-bold text-blue-600">نمایش همه پسوندها</a></div>
        @endforelse</div><div class="mt-5">{{ $extensions->links() }}</div>
    </section>
    @else
    <section class="domain-card"><h2 class="domain-heading">پیگیری سفارش‌ها</h2><form method="GET" class="mt-4 flex gap-3"><input type="hidden" name="section" value="orders">@foreach(request()->only('extension', 'provider', 'catalog', 'prices_page') as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach<label for="order-status" class="sr-only">وضعیت سفارش</label><select id="order-status" name="status" class="domain-input max-w-xs"><option value="">همه وضعیت‌ها</option>@foreach(['needs_review'=>'نیازمند بررسی', 'confirming'=>'در حال تأیید', 'processing'=>'در حال انجام', 'completed'=>'تکمیل شده', 'failed'=>'ناموفق', 'awaiting_payment'=>'در انتظار پرداخت', 'draft'=>'پیش‌نویس'] as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select><button class="domain-secondary">فیلتر سفارش‌ها</button></form>
        <div class="mt-5 divide-y divide-slate-100">@forelse($orders as $order)<article class="py-5"><div class="flex flex-wrap items-center justify-between gap-3 text-sm"><div><p dir="ltr" class="break-all text-right font-black">{{ $order->domain }}</p><p class="domain-help">{{ $order->customer->name }} · {{ $providerLabels[$order->provider] }} · {{ $order->operation }} · {{ $order->period }} سال · DOM-{{ $order->id }}</p></div><span class="domain-badge">{{ $order->label() }}</span><span class="font-bold">{{ $wallets->format($order->total, $order->currency) }}</span>@if($order->invoice_id)<a class="text-blue-600" href="{{ route('admin.billing.invoices.show', $order->invoice_id) }}">فاکتور</a>@endif</div>
            <p class="domain-help mt-2">تراکنش رزرو: {{ $order->wallet_transaction_id ?: '—' }} · آزادسازی: {{ $order->refund_transaction_id ?: '—' }} · هزینه تأمین: {{ $wallets->format($order->supplier_price, 'IRR') }}</p>
            @foreach($order->operations as $operation)<div class="mt-3 rounded-xl bg-slate-50 p-4"><p class="text-xs">{{ $operation->kind }} · {{ $operation->status }} · پیگیری {{ $operation->track_id ?: 'نامشخص' }} · {{ $operation->result_code }}</p>@if($order->provider === 'reseller_world' && in_array($operation->status, ['submitting','uncertain']) && $order->isProcessing())<form method="POST" action="{{ route('admin.settings.domains.orders.reconcile', $order) }}" class="mt-3 flex flex-wrap gap-3">@csrf<input type="hidden" name="operation_id" value="{{ $operation->id }}"><label class="sr-only" for="track-{{ $operation->id }}">شناسه پیگیری تأییدشده</label><input id="track-{{ $operation->id }}" name="track_id" required pattern="[0-9]+" placeholder="شناسه پیگیری تأییدشده سرویس‌دهنده" class="domain-input max-w-sm"><button class="domain-secondary">اتصال پیگیری؛ بدون ارسال مجدد</button></form>@endif</div>@endforeach
            @if($order->provider === 'reseller_world' && $order->status === 'needs_review' && !$order->operations->contains(fn($operation) => in_array($operation->status, ['submitting','uncertain'])))<form method="POST" action="{{ route('admin.settings.domains.orders.recheck', $order) }}" class="mt-3">@csrf<button class="domain-secondary">بررسی مجدد نتیجه عملیات قبلی</button></form>@endif
            @if($order->provider === 'irpower' && $order->status === 'needs_review')
                @adminAbility('wallet.manage')<details class="mt-4 rounded-xl border border-blue-200 p-4"><summary class="cursor-pointer text-sm font-bold text-blue-800">تأیید نتیجه ثبت یا تمدید IRPOWER</summary><p class="domain-help mt-3">این API تاریخ انقضا یا پیگیری سفارش را ارائه نمی‌کند. ابتدا همین دامنه، مالک، انجام کامل عملیات و تاریخ انقضا را در حساب نمایندگی تأیید کنید. وضعیت آزاد نبودن در WHOIS به‌تنهایی کافی نیست. تاریخ تخمینی وارد نکنید.</p><form method="POST" action="{{ route('admin.settings.domains.orders.irpower-confirm', $order) }}" class="mt-4 space-y-3">@csrf<label class="domain-label">تاریخ انقضای تأییدشده (میلادی)<input name="expires_at" type="date" required class="domain-input" dir="ltr"></label><label class="domain-label">مرجع تأیید حساب نمایندگی یا تیکت<input name="provider_reference" required minlength="10" maxlength="500" class="domain-input"></label><label class="flex items-start gap-2 text-xs leading-7"><input name="verified" type="checkbox" value="1" required class="mt-2 accent-blue-600">انجام کامل ثبت یا تمدید همین سفارش، مالک و تاریخ انقضا را تأیید کردم.</label><button class="domain-primary">تکمیل سفارش و صدور فاکتور؛ بدون ارسال مجدد</button></form></details>@endadminAbility
            @endif
            @if($order->status === 'needs_review' && $order->operations->contains(fn($operation) => (in_array($operation->status, ['uncertain','submitting']) || ($order->provider === 'irpower' && $operation->kind === $order->operation && $operation->status === 'done' && $operation->result_code === 'provider_accepted')) && !$operation->track_id))
                @adminAbility('wallet.manage')<details class="mt-4 rounded-xl border border-amber-200 p-4"><summary class="cursor-pointer text-xs font-bold text-amber-800">آزادسازی پس از تأیید انجام نشدن توسط سرویس‌دهنده</summary><p class="domain-help mt-3">تنها پس از دریافت تأیید قطعی از سرویس‌دهنده مبنی بر انجام نشدن همین سفارش استفاده کنید. تأخیر یا خطای ارتباط، مدرک انجام نشدن نیست.</p><form method="POST" action="{{ route('admin.settings.domains.orders.not-submitted', $order) }}" class="mt-3 space-y-3">@csrf<label class="domain-label" for="provider-reference-{{ $order->id }}">شماره تیکت یا مرجع تأیید سرویس‌دهنده</label><input id="provider-reference-{{ $order->id }}" name="provider_reference" required minlength="10" maxlength="500" class="domain-input"><label class="flex items-start gap-2 text-xs leading-7"><input type="checkbox" name="confirmed_not_executed" value="1" required class="mt-2 accent-blue-600">انجام نشدن سفارش با سرویس‌دهنده تأیید شده است و مرجع بالا را ثبت کردم.</label><button class="domain-secondary">ثبت نتیجه و آزادسازی مبلغ رزرو</button></form></details>@endadminAbility
            @endif
        </article>@empty<p class="py-8 text-sm text-slate-500">هنوز سفارشی در این وضعیت وجود ندارد.</p>@endforelse</div><div class="mt-5">{{ $orders->links() }}</div>
    </section>
    @if($irpowerDomains->isNotEmpty())<section class="domain-card"><h2 class="domain-heading">تاریخ اعتبار دامنه‌های IRPOWER</h2><p class="domain-help mt-2">تاریخ‌ها از نتیجه تأییدشده ثبت یا تمدید نگهداری می‌شوند. تغییر خارج از Aviato را پس از بررسی حساب نمایندگی در اینجا ثبت کنید.</p>@foreach($irpowerDomains as $managed)<details class="mt-4 rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer text-sm"><span dir="ltr" class="font-bold">{{ $managed->name }}</span> · انقضا {{ \App\Support\Jalali::format($managed->expires_at, 'Y/m/d') }}</summary>@adminAbility('wallet.manage')<form method="POST" action="{{ route('admin.settings.domains.expiry', $managed) }}" class="mt-4 space-y-3">@csrf<label class="domain-label">تاریخ انقضای تأییدشده (میلادی)<input type="date" name="expires_at" required class="domain-input" dir="ltr" value="{{ $managed->expires_at?->toDateString() }}"></label><label class="domain-label">مرجع تأیید<input name="provider_reference" minlength="10" maxlength="500" required class="domain-input"></label><label class="flex items-start gap-2 text-xs"><input type="checkbox" name="verified" value="1" required>تاریخ و دامنه در حساب نمایندگی تأیید شده‌اند.</label><button class="domain-secondary">ثبت تاریخ تأییدشده</button></form>@endadminAbility</details>@endforeach<div class="mt-4">{{ $irpowerDomains->links() }}</div></section>@endif
    @if($uncertainOperations->isNotEmpty())<section class="domain-card"><h2 class="domain-heading">تغییر نیم‌سرور نیازمند پیگیری</h2>@foreach($uncertainOperations as $operation)<form method="POST" action="{{ route('admin.settings.domains.operations.reconcile', $operation) }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf<span dir="ltr" class="text-sm font-bold">{{ $operation->domain->name }}</span><label class="sr-only" for="ns-track-{{ $operation->id }}">شناسه پیگیری</label><input id="ns-track-{{ $operation->id }}" name="track_id" required pattern="[0-9]+" class="domain-input max-w-xs" placeholder="شناسه پیگیری تأییدشده"><button class="domain-secondary">اتصال پیگیری</button></form>@endforeach</section>@endif
    @endif
</div>
<script>
    (() => {
        const key = 'domain-commerce:' + location.pathname + location.search;
        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (saved) {
                document.querySelectorAll('[data-domain-editor] details').forEach((editor, index) => { if ((saved.nested || []).includes(index)) editor.open = true; });
                saved.open.forEach(id => { const editor = document.getElementById(id); if (editor) editor.open = true; });
                requestAnimationFrame(() => window.scrollTo(0, saved.scroll));
                sessionStorage.removeItem(key);
            }
            document.querySelectorAll('[data-domain-editor] form').forEach(form => {
                form.addEventListener('submit', () => sessionStorage.setItem(key, JSON.stringify({
                    open: Array.from(document.querySelectorAll('[data-domain-editor][open]')).map(editor => editor.id),
                    nested: Array.from(document.querySelectorAll('[data-domain-editor] details')).flatMap((editor, index) => editor.open ? [index] : []),
                    scroll: window.scrollY,
                })));
            });
        } catch (_) {}
    })();
</script>
@endsection
