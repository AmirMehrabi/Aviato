@extends('customer.layout')
@section('own_messages', '1')
@section('title', 'مدیریت دامنه')
@section('header_title', 'مدیریت دامنه')
@section('header_subtitle', 'مدیریت تمدید، نیم‌سرورها و سفارش‌های دامنه')
@section('breadcrumbs')
    @include('customer.domains.partials.breadcrumbs', [
        'parents' => [['دامنه‌ها', route('customer.domains.index', request()->only(['filter', 'q', 'page']), false)]],
        'current' => $domain->name,
        'ltr' => true,
    ])
@endsection
@php
    $activeNav = 'domains';
@endphp
@section('content')
<div class="space-y-6">
    @if(session('status'))<p role="status" class="domain-notice">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="domain-error">{{ $errors->first() }}</div>@endif
    @php
        $canRenew = $canPayDomain && $domain->status === 'active' && $domain->expires_at?->isFuture() && $renewals->isNotEmpty();
    @endphp
    <section class="domain-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 dir="ltr" class="break-all text-start text-2xl font-black">{{ $domain->name }}</h2>
                <span class="domain-badge mt-2 {{ $domain->needsAttention() ? 'text-amber-800' : 'text-emerald-700' }}">{{ $domain->customerStatus() }}</span>
            </div>
            @if($domain->needsAttention() && $canRenew)
                <a href="#renewal" class="domain-primary">بررسی قیمت تمدید</a>
            @elseif($domain->needsAttention() && ($domain->status !== 'active' || !$domain->expires_at || $domain->expires_at->isPast()))
                <a href="{{ route('customer.tickets.index', [], false) }}" class="domain-primary">پیگیری با پشتیبانی</a>
            @else
                <a href="#dns" class="domain-primary">{{ $dnsZone ? 'وضعیت اتصال DNS' : 'DNS و نیم‌سرورها' }}</a>
            @endif
        </div>
        <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="domain-help">تاریخ انقضا</dt>
                <dd class="mt-1 font-black">{{ $domain->expires_at ? \App\Support\Jalali::format($domain->expires_at, 'Y/m/d') : 'در انتظار تأیید' }}</dd>
                @if($domain->expires_at?->isFuture())
                    <dd class="domain-help">{{ (int) ceil(now()->diffInDays($domain->expires_at)) }} روز باقی مانده</dd>
                @endif
            </div>
            <div>
                <dt class="domain-help">تمدید خودکار</dt>
                <dd class="mt-1 font-bold">{{ $domain->auto_renew ? 'فعال' : 'خاموش' }}</dd>
                @if($domain->auto_renew && $domain->auto_renew_consented_at && $domain->status === 'active' && $domain->expires_at?->isFuture() && $domain->expires_at->copy()->subDays(7)->isFuture())
                    <dd class="domain-help">شروع بازه تمدید: {{ \App\Support\Jalali::format($domain->expires_at->copy()->subDays(7), 'Y/m/d') }}</dd>
                @endif
            </div>
            <div>
                <dt class="domain-help">آخرین همگام‌سازی</dt>
                <dd class="mt-1">{{ \App\Support\Jalali::format($domain->synced_at) }}</dd>
            </div>
        </dl>
    </section>
    <nav aria-label="بخش‌های مدیریت دامنه" class="flex flex-wrap gap-2">
        <a href="#renewal" class="domain-secondary">تمدید</a>
        <a href="#dns" class="domain-secondary">DNS و نیم‌سرورها</a>
        <a href="#history" class="domain-secondary">سفارش‌ها و فاکتورها</a>
    </nav>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section id="renewal" class="domain-card scroll-mt-6"><h2 class="domain-heading">تمدید دامنه</h2><p class="domain-help mt-2">برای جلوگیری از وقفه، قبل از تاریخ انقضا تمدید کنید. قیمت نهایی در مرحله بررسی نمایش داده می‌شود.</p>
            @if(!$canPayDomain)<p class="domain-help mt-4">تمدید و پرداخت این دامنه بر عهده {{ $domain->customer->name }} است.</p>@elseif($canRenew)<form method="POST" action="{{ route('customer.domains.renew', $domain, false) }}" class="mt-5 space-y-4">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><label for="renew-period" class="domain-label">مدت تمدید</label><select id="renew-period" name="period" class="domain-input">@foreach($renewals as $product)<option value="{{ $product->period }}">{{ $product->period }} سال</option>@endforeach</select><button class="domain-primary w-full">بررسی قیمت و تمدید</button></form>@else<p class="domain-notice mt-4">برای بررسی تمدید یا بازیابی این دامنه با پشتیبانی تماس بگیرید. امکان بازیابی به قوانین رجیستری بستگی دارد.</p>@endif
        </section>
        <section class="domain-card"><h2 class="domain-heading">تمدید خودکار، با انتخاب شما</h2><p class="domain-help mt-2">۷ روز پیش از انقضا، درخواست تمدید یک‌ساله را از کیف پول شخصی پرداخت‌کننده دامنه ({{ $domain->customer->name }}) انجام می‌دهیم؛ فقط در صورت موجودی کافی و قیمت کمتر از سقف تعیین‌شده. برداشت بانکی خودکار انجام نمی‌شود.</p>@if($canPayDomain)<form method="POST" action="{{ route('customer.domains.auto-renew', $domain, false) }}" class="mt-5 space-y-4" x-data="{ enabled: @js((bool) old('enabled', $domain->auto_renew)) }">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"> @method('PATCH')<input type="hidden" name="enabled" value="0"><label class="flex items-center gap-3 text-sm font-bold"><input type="checkbox" name="enabled" value="1" x-model="enabled" class="accent-blue-600"> تمدید خودکار فعال باشد</label><div x-show="enabled"><label for="renew-limit" class="domain-label">حداکثر مبلغ هر تمدید یک‌ساله (تومان، با مالیات)</label><input id="renew-limit" type="number" name="limit_toman" min="1" max="50000000" value="{{ old('limit_toman', $domain->auto_renew_limit ? intdiv($domain->auto_renew_limit, 10) : '') }}" :required="enabled" class="domain-input"><label class="mt-4 flex items-start gap-3 text-xs leading-7"><input type="checkbox" name="consent" value="1" :required="enabled" class="mt-2 accent-blue-600">اجازه رزرو موجودی کیف پول و تمدید یک‌ساله تا سقف بالا را می‌دهم.</label></div><button class="domain-secondary w-full">ذخیره تنظیمات تمدید</button></form>@else<p class="domain-help mt-4">تمدید خودکار {{ $domain->auto_renew ? 'روشن' : 'خاموش' }} است. تغییر این تنظیم و پرداخت بر عهده {{ $domain->customer->name }} است.</p>@endif</section>
        <section id="dns" class="domain-card scroll-mt-6"><h2 class="domain-heading">DNS و نیم‌سرورها</h2><p class="domain-help mt-2">این بخش اتصال به سرویس DNS را تغییر می‌دهد. مدیریت رکوردهای A و MX در سرویس DNS شما انجام می‌شود.</p>
            @if($dnsZone)
                <a href="{{ route('customer.cloud-dns.show', $dnsZone, false) }}" class="domain-secondary mt-4">مشاهده وضعیت اتصال و رکوردهای DNS</a>
            @elseif($canReadDns && $domain->status === 'active')
                <a href="{{ route('customer.cloud-dns.index', [], false) }}" class="domain-secondary mt-4">باز کردن DNS ابری</a>
            @endif
            @if($canManageDomain && $domain->status === 'active')<form method="POST" action="{{ route('customer.domains.nameservers', $domain, false) }}" class="mt-5 space-y-4">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"> @method('PATCH')@include('customer.domains._nameservers', ['initialNameservers' => $domain->nameservers, 'maxNameservers' => $domain->provider === 'irpower' ? 5 : 6])<button class="domain-secondary w-full">ثبت درخواست تغییر</button></form>@else<ul class="mt-4 space-y-2">@foreach($domain->nameservers ?? [] as $nameserver)<li dir="ltr" class="break-all text-sm">{{ $nameserver }}</li>@endforeach</ul><p class="domain-help mt-4">{{ $domain->status !== 'active' ? 'تغییر نیم‌سرورها پس از فعال شدن دامنه در دسترس خواهد بود.' : 'تغییر نیم‌سرورها بر عهده مالک یا مدیر فضای کاری است.' }}</p>@endif
            @foreach($operations->where('kind', 'nameservers') as $operation)<p class="domain-help mt-4">تغییر نیم‌سرور · {{ match($operation->status) { 'done'=>'انجام شد', 'failed'=>'انجام نشد؛ تنظیمات قبلی حفظ شد', 'uncertain','submitting'=>'در حال پیگیری؛ دوباره ارسال نکنید', default=>'در انتظار تأیید' } }}</p>@endforeach
        </section>
        @if($canManageDomain || $canPayDomain)<section class="domain-card"><h2 class="domain-heading">مالک دامنه</h2><p class="mt-4 text-sm font-bold">{{ $domain->registrant['name'] ?? $domain->registrant['holder'] ?? '' }}</p><p dir="ltr" class="mt-2 break-all text-right text-sm text-slate-500">{{ $domain->registrant['email'] ?? '' }}</p><p class="domain-help mt-4">برای تغییر مالکیت یا اطلاعات تماس، درخواست پشتیبانی ثبت کنید. اگر ایمیل تأیید مالکیت دریافت کرده‌اید، آن را تکمیل کنید.</p><a href="{{ route('customer.tickets.index', [], false) }}" class="domain-secondary mt-5">ارتباط با پشتیبانی</a></section>@endif
    </div>
    <section id="history" class="domain-card scroll-mt-6"><h2 class="domain-heading">سفارش‌ها و فاکتورها</h2><div class="mt-4 divide-y divide-slate-100">@forelse($orders as $order)<a class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm hover:text-blue-600" href="{{ route('customer.domains.orders.show', $order, false) }}"><span>{{ $order->operation === 'renew' ? 'تمدید' : 'ثبت' }} · {{ $order->period }} سال</span><span>{{ $order->label() }}</span><span class="font-bold">@if($canViewDomainBilling){{ $wallets->format($order->total, $order->currency) }}@else مشاهده وضعیت @endif ←</span></a>@empty<p class="domain-help py-4">هنوز سفارشی برای این دامنه وجود ندارد.</p>@endforelse</div></section>
</div>
@endsection
