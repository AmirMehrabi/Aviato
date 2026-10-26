@extends('layouts.admin')

@section('title', 'دامنه‌ها و دسترسی مشتریان')

@section('content')
<div class="space-y-6 px-4 py-6 md:px-8 lg:px-10">
    <a href="{{ route('admin.settings.edit') }}" class="text-sm font-black text-[#0069FF]">→ بازگشت به تنظیمات</a>
    <div>
        <h1 class="text-2xl font-black text-slate-950">دامنه‌ها و دسترسی مشتریان</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">بخش دامنه فقط برای مشتریانی نمایش داده می‌شود که در فهرست زیر انتخاب کرده‌اید. سایر مشتریان به صفحه و استعلام دسترسی ندارند.</p>
    </div>
    @if (session('status'))<div role="status" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-bold text-blue-900">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <h2 class="text-lg font-black">اتصال سرویس دامنه</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">فروش دامنه پس از تنظیم قیمت و روشن کردن کلید فروش برای مشتریان منتخب فعال می‌شود. دامنه‌های خریداری‌شده همچنان قابل مدیریت و تمدید هستند.</p>
        <form action="{{ route('admin.settings.domains.update') }}" method="POST" class="mt-5 space-y-5">
            @csrf @method('PATCH')
            <input type="hidden" name="enabled" value="0">
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled)) class="mt-1 size-4 rounded border-slate-300 accent-blue-600">
                <span><span class="block text-sm font-black">فعال‌سازی بخش دامنه برای مشتریان منتخب</span><span class="mt-1 block text-xs leading-6 text-slate-500">خاموش کردن این گزینه، جستجو و خرید تازه را متوقف می‌کند. مدیریت و تمدید دامنه‌های قبلی حفظ می‌شود.</span></span>
            </label>
            <div class="max-w-2xl">
                <label for="domain-reseller-token" class="block text-sm font-bold text-slate-700">توکن Reseller World</label>
                <input id="domain-reseller-token" type="password" name="domain_reseller_token" value="" autocomplete="new-password" spellcheck="false" dir="ltr" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" aria-describedby="domain-token-help" placeholder="{{ $tokenConfigured ? '•••••••• — configured' : 'Bearer token' }}">
                <p id="domain-token-help" class="mt-2 text-xs leading-6 text-slate-500">{{ $tokenConfigured ? 'توکن ذخیره شده است. برای حفظ توکن فعلی، این فیلد را خالی بگذارید.' : 'توکن اتصال را وارد کنید.' }} توکن به‌صورت رمزنگاری‌شده در پایگاه داده نگهداری می‌شود.</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-5 space-y-4">
                <h3 class="font-black">سرویس‌دهنده‌های ثبت و تمدید</h3>
                <p class="domain-help">جستجوی بین‌المللی از Reseller.world و جستجوی ‎.ir از ایرنیک انجام می‌شود. کلید اتصال برای مدیریت دامنه‌های قبلی نیز لازم است؛ آن را با حساب دیگری جایگزین نکنید.</p>
                <div><label for="irpower-api-key" class="domain-label">کلید IRPOWER</label><input id="irpower-api-key" name="irpower_api_key" type="password" value="" autocomplete="new-password" spellcheck="false" dir="ltr" class="domain-input" placeholder="{{ filled(\App\Models\AppSetting::getValue(\App\Models\AppSetting::IRPOWER_API_KEY)) ? '•••••••• — configured' : 'X-Api-Key' }}"><p class="domain-help mt-2">کلید به‌صورت رمزنگاری‌شده ذخیره می‌شود. خالی گذاشتن این فیلد، کلید فعلی را حفظ می‌کند.</p></div>
                @foreach(['reseller_world' => 'Reseller.world', 'irpower' => 'IRPOWER'] as $providerKey => $providerLabel)
                    <input type="hidden" name="{{ $providerKey }}_enabled" value="0"><label class="flex items-start gap-3 text-sm"><input type="checkbox" name="{{ $providerKey }}_enabled" value="1" @checked(\App\Models\AppSetting::getValue('domains.'.$providerKey.'_enabled', $providerKey === 'reseller_world')) class="mt-1 accent-blue-600">{{ $providerLabel }} برای ثبت‌های تازه در انتخاب قیمت شرکت کند</label>
                @endforeach
                <div><label for="domain-routing" class="domain-label">انتخاب خودکار برای سفارش‌های تازه</label><select id="domain-routing" name="routing_policy" class="domain-input">@foreach(['cheapest'=>'کمترین قیمت فروش سال اول', 'two_year'=>'کمترین مجموع قیمت فروش ثبت و یک تمدید سالانه', 'reseller_world'=>'همیشه Reseller.world', 'irpower'=>'همیشه IRPOWER'] as $policy=>$label)<option value="{{ $policy }}" @selected(\App\Models\AppSetting::getValue('domains.routing_policy', 'cheapest') === $policy)>{{ $label }}</option>@endforeach</select><p class="domain-help mt-2">قیمت تمدید از همان سرویس‌دهنده ثبت نمایش داده می‌شود. مسیر هر پسوند را نیز می‌توانید در کاتالوگ تغییر دهید.</p></div>
            </div>
            <input type="hidden" name="sales_enabled" value="0"><label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4"><input type="checkbox" name="sales_enabled" value="1" @checked(\App\Models\AppSetting::getValue('domains.sales_enabled', false)) class="mt-1 accent-blue-600"><span class="text-sm font-bold">فعال‌سازی خرید دامنه با قیمت‌های تأییدشده <span class="mt-1 block text-xs font-normal text-slate-500">فقط پسوندهایی که در کاتالوگ فعال کرده‌اید فروخته می‌شوند. تمدید دامنه‌های قبلی مستقل از این گزینه است.</span></span></label>
            <div><label for="default-ns" class="block text-sm font-bold">نیم‌سرورهای پیش‌فرض (اختیاری، هر کدام در یک خط)</label><textarea id="default-ns" name="default_nameservers" dir="ltr" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">{{ implode("\n", \App\Models\AppSetting::getValue('domains.default_nameservers', [])) }}</textarea><p class="mt-2 text-xs text-slate-500">فقط نیم‌سرورهایی را وارد کنید که سرویس DNS آن‌ها واقعاً برای دامنه مشتری آماده است.</p></div>
            <a href="{{ route('admin.settings.domains.commerce') }}" class="inline-block text-sm font-black text-blue-600">مدیریت قیمت‌ها، سفارش‌ها و گزارش مالی ←</a>
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">ذخیره تنظیمات اتصال</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="text-lg font-black">مشتریان منتخب</h2><p class="mt-2 text-sm text-slate-500">دسترسی به حساب مشتری تعلق دارد و با تغییر فضای کاری منتقل نمی‌شود.</p></div>
            <span class="rounded-full bg-blue-50 px-4 py-2 text-xs font-black text-blue-700">{{ number_format($selectedCount) }} مشتری انتخاب شده</span>
        </div>
        <form method="GET" action="{{ route('admin.settings.domains.edit') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1"><label for="domain-customer-search" class="mb-2 block text-xs font-bold">جستجوی مشتری</label><input id="domain-customer-search" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="نام، ایمیل یا شماره موبایل" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-blue-500"></div>
            <div><label for="domain-access-filter" class="mb-2 block text-xs font-bold">نمایش</label><select id="domain-access-filter" name="access" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><option value="">همه مشتریان</option value="selected" @selected(request('access') === 'selected')>فقط مشتریان منتخب</option></select></div>
            <button class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold hover:bg-slate-50">جستجو</button>
            @if (request('q') || request('access'))<a href="{{ route('admin.settings.domains.edit') }}" class="px-2 py-3 text-sm font-bold text-blue-600">پاک کردن فیلتر</a>@endif
        </form>
        <div class="mt-6 divide-y divide-slate-100">
            @forelse ($customers as $domainCustomer)
                <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0"><p class="text-sm font-black">{{ $domainCustomer->name }} @if ($domainCustomer->isSuspended())<span class="text-xs font-normal text-red-600">(حساب معلق)</span>@endif</p><p dir="ltr" class="mt-1 break-all text-right text-xs text-slate-500">{{ $domainCustomer->email ?: $domainCustomer->phone }}</p></div>
                    <form method="POST" action="{{ route('admin.settings.domains.access', $domainCustomer) }}" class="flex shrink-0 items-center gap-3">
                        @csrf @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $domainCustomer->domain_management_enabled ? 0 : 1 }}">
                        <span class="text-xs font-bold {{ $domainCustomer->domain_management_enabled ? 'text-emerald-700' : 'text-slate-400' }}">{{ $domainCustomer->domain_management_enabled ? 'انتخاب شده' : 'بدون دسترسی' }}</span>
                        <button aria-label="{{ ($domainCustomer->domain_management_enabled ? 'لغو دسترسی دامنه برای ' : 'فعال‌سازی دامنه برای ').$domainCustomer->name }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-black {{ $domainCustomer->domain_management_enabled ? 'text-red-600 hover:bg-red-50' : 'text-blue-600 hover:bg-blue-50' }} focus-visible:outline-2 focus-visible:outline-blue-600">{{ $domainCustomer->domain_management_enabled ? 'لغو دسترسی' : 'فعال‌سازی' }}</button>
                    </form>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-slate-500">مشتری‌ای با این مشخصات پیدا نشد.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $customers->links() }}</div>
    </section>
</div>
@endsection
