<section id="my-domains" class="domain-card scroll-mt-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="domain-heading">دامنه‌های فضای کاری</h1><p class="domain-help mt-2">وضعیت و تاریخ اعتبار دامنه‌ها را ببینید و تنظیمات هر دامنه را مدیریت کنید.</p></div>
    </div>
    @if($domainCount)
        <nav aria-label="فیلتر دامنه‌ها" class="mt-6 flex flex-wrap gap-2">
            @foreach(['all' => ['همه', $domainCount], 'expiring' => ['نزدیک به انقضا', $expiringCount], 'attention' => ['نیازمند توجه', $attentionCount]] as $filter => [$label, $count])
                <a href="{{ request()->fullUrlWithQuery(['filter' => $filter]) }}" @if(request('filter', 'all') === $filter) aria-current="page" @endif class="rounded-xl border px-4 py-2 text-sm font-bold {{ request('filter', 'all') === $filter ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
        </nav>
        <form method="GET" class="mt-4 flex flex-wrap gap-3">
            <input type="hidden" name="filter" value="{{ request('filter', 'all') }}">
            <label for="owned-domain-search" class="sr-only">جستجو در دامنه‌های این فضای کاری</label>
            <input id="owned-domain-search" name="q" value="{{ request('q') }}" placeholder="example.com" dir="ltr" class="domain-input min-w-0 flex-1" maxlength="253">
            <button class="domain-secondary">جستجو در دامنه‌ها</button>
        </form>
    @endif
    <div class="mt-5 divide-y divide-slate-100">
        @forelse($ownedDomains as $domain)
            <article class="flex flex-wrap items-center justify-between gap-4 py-5">
                <div class="min-w-0"><a href="{{ route('customer.domains.show', $domain, false) }}" dir="ltr" class="block break-all text-lg font-black text-slate-950 hover:text-blue-600">{{ $domain->name }}</a><p class="mt-2 text-xs font-bold {{ $domain->needsAttention() ? 'text-amber-700' : 'text-emerald-700' }}">{{ $domain->customerStatus() }}</p></div>
                <div class="text-sm"><p class="text-slate-500">تاریخ انقضا</p><p class="mt-1 font-bold">{{ $domain->expires_at ? \App\Support\Jalali::format($domain->expires_at, 'Y/m/d') : 'در انتظار تأیید' }}</p>@if($domain->expires_at?->isFuture())<p class="mt-1 text-xs text-slate-500">{{ (int) ceil(now()->diffInDays($domain->expires_at)) }} روز باقی مانده</p>@endif</div>
                <p class="text-xs text-slate-500">تمدید خودکار: <span class="font-bold {{ $domain->auto_renew ? 'text-emerald-700' : 'text-slate-600' }}">{{ $domain->auto_renew ? 'روشن' : 'خاموش' }}</span></p>
                <a href="{{ route('customer.domains.show', $domain, false) }}{{ $domain->needsAttention() ? '#renewal' : '' }}" class="domain-secondary">{{ $domain->needsAttention() ? 'بررسی و مدیریت' : 'مدیریت دامنه' }} ←</a>
            </article>
        @empty
            <div class="py-8 text-center"><h2 class="font-black">{{ $domainCount ? 'دامنه‌ای با این فیلتر پیدا نشد' : 'هنوز دامنه‌ای در این فضای کاری ندارید' }}</h2><p class="domain-help mt-3">{{ $domainCount ? 'نام یا فیلتر را تغییر دهید تا دامنه‌های دیگر را ببینید.' : 'دامنه‌های هر فضای کاری در همین بخش نمایش داده می‌شوند. برای دیدن دامنه‌های دیگر، فضای کاری را از فهرست بالای پنل تغییر دهید.' }}</p>@if($domainCount)<a href="{{ route('customer.domains.index', [], false) }}" class="domain-secondary mt-4">نمایش همه دامنه‌ها</a>@elseif(!$canSearch)<p class="domain-help mt-4">{{ (int) $activeProject->owner_customer_id === (int) $customer->id ? 'ثبت دامنه در حال حاضر برای این فضای کاری در دسترس نیست. برای راهنمایی با پشتیبانی تماس بگیرید.' : 'برای ثبت دامنه در این فضای کاری از مالک آن کمک بگیرید.' }}</p>@endif</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $ownedDomains->links() }}</div>
</section>
