@php
    $listQuery = request()->only(['filter', 'q', 'page']);
    $filters = ['all' => ['همه', $domainCount], 'expiring' => ['انقضا تا ۳۰ روز', $expiringCount], 'attention' => ['نیازمند توجه', $attentionCount]];
@endphp
<section id="my-domains" class="domain-card scroll-mt-6">
    <h1 class="domain-heading">دامنه‌های فضای کاری</h1>
    @if($domainCount)
        <div class="my-5 flex flex-wrap items-end justify-between gap-4">
            <nav aria-label="فیلتر دامنه‌ها" class="flex flex-wrap gap-2">
                @foreach($filters as $filter => [$label, $count])
                    <a href="{{ route('customer.domains.index', ['filter' => $filter, 'q' => request('q')], false) }}"
                        @if(request('filter', 'all') === $filter) aria-current="page" @endif
                        class="rounded-xl border px-3 py-2 text-sm font-bold {{ request('filter', 'all') === $filter ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                        {{ $label }} ({{ $count }})
                    </a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('customer.domains.index', [], false) }}" class="w-full max-w-sm">
                <input type="hidden" name="filter" value="{{ request('filter', 'all') }}">
                <label for="owned-domain-search" class="domain-label">جستجو در دامنه‌های من</label>
                <div class="flex gap-2">
                    <input id="owned-domain-search" name="q" value="{{ request('q') }}" placeholder="example.com" dir="ltr" class="domain-input min-w-0 flex-1" maxlength="253">
                    <button class="domain-secondary">جستجو</button>
                </div>
            </form>
        </div>
    @endif

    @if($ownedDomains->isNotEmpty())
        <table class="block w-full text-start text-sm sm:table">
            <thead class="sr-only sm:not-sr-only sm:table-header-group">
                <tr class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <th scope="col" class="px-3 py-3 text-start">دامنه و وضعیت</th>
                    <th scope="col" class="px-3 py-3 text-start">تاریخ انقضا</th>
                    <th scope="col" class="px-3 py-3 text-start">تمدید خودکار</th>
                    <th scope="col" class="px-3 py-3 text-start"><span class="sr-only">اقدام</span></th>
                </tr>
            </thead>
            <tbody class="block divide-y divide-slate-100 sm:table-row-group">
                @foreach($ownedDomains as $domain)
                    @php
                        $details = route('customer.domains.show', ['domain' => $domain] + $listQuery, false);
                        $urgent = $domain->needsAttention();
                    @endphp
                    <tr class="grid grid-cols-2 gap-3 py-4 sm:table-row">
                        <th scope="row" class="col-span-2 min-w-0 text-start sm:px-3 sm:py-4">
                            <bdi dir="ltr" class="break-all font-black text-slate-950">{{ $domain->name }}</bdi>
                            <span class="mt-1 block text-xs font-bold {{ $urgent ? 'text-amber-800' : 'text-emerald-700' }}">{{ $domain->customerStatus() }}</span>
                        </th>
                        <td class="sm:px-3 sm:py-4">
                            <span class="block text-xs text-slate-500 sm:hidden">تاریخ انقضا</span>
                            <bdi class="font-bold">{{ $domain->expires_at ? \App\Support\Jalali::format($domain->expires_at, 'Y/m/d') : 'در انتظار تأیید' }}</bdi>
                            @if($domain->expires_at?->isFuture())
                                <span class="block text-xs text-slate-500">{{ (int) ceil(now()->diffInDays($domain->expires_at)) }} روز باقی مانده</span>
                            @endif
                        </td>
                        <td class="sm:px-3 sm:py-4">
                            <span class="block text-xs text-slate-500 sm:hidden">تمدید خودکار</span>
                            {{ $domain->auto_renew ? 'روشن' : 'خاموش' }}
                        </td>
                        <td class="col-span-2 sm:px-3 sm:py-4 sm:text-end">
                            <a href="{{ $details }}{{ $urgent ? '#renewal' : '' }}" class="domain-secondary">
                                {{ $urgent ? 'بررسی وضعیت' : 'مدیریت' }} <span class="sr-only">{{ $domain->name }}</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $ownedDomains->links() }}</div>
    @else
        <div class="py-8 text-center">
            @if($domainCount)
                <h2 class="font-black">دامنه‌ای با این جستجو یا فیلتر پیدا نشد</h2>
                <a href="{{ route('customer.domains.index', [], false) }}" class="domain-secondary mt-4">پاک کردن فیلترها</a>
            @else
                <h2 class="font-black">هنوز دامنه‌ای در این فضای کاری ندارید</h2>
                @if($canSearch && $pendingOrders->isEmpty())
                    <a href="{{ route('customer.domains.register', [], false) }}" class="domain-primary mt-4">اولین دامنه‌تان را پیدا کنید</a>
                @elseif(!$canSearch)
                    <p class="domain-help mt-3">{{ (int) $activeProject->owner_customer_id === (int) $customer->id ? 'ثبت دامنه در حال حاضر برای این فضای کاری در دسترس نیست.' : 'برای ثبت دامنه از مالک فضای کاری کمک بگیرید.' }}</p>
                @endif
            @endif
        </div>
    @endif
</section>
