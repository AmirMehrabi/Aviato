<section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="خلاصه بازه انتخاب‌شده">
    @if ($finance !== null)
        @adminRoute('admin.billing.payments.index')
            <a href="{{ route('admin.billing.payments.index', $reportRange + ['date_basis' => 'paid', 'status' => 'successful']) }}" class="rounded-2xl border border-blue-200 bg-blue-50/50 p-5 transition hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                <h2 class="text-sm font-semibold text-slate-600">وصول موفق درگاه</h2>
                <div class="mt-3 space-y-1 text-2xl font-bold text-slate-950">
                    @forelse ($finance['collected'] as $money)
                        <p>{{ $money['formatted'] }} <span class="text-xs font-medium" dir="ltr">{{ $money['currency'] }}</span></p>
                    @empty
                        <p>{{ $wallets->format(0) }}</p>
                    @endforelse
                </div>
                <p class="mt-2 text-xs text-slate-500">شارژ کیف پول · براساس زمان پرداخت</p>
            </a>
        @endadminRoute
        @adminRoute('admin.billing.usage.index')
            <a href="{{ route('admin.billing.usage.index', $reportRange + ['state' => 'settled']) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                <h2 class="text-sm font-semibold text-slate-600">مصرف تسویه‌شده خدمات</h2>
                <p class="mt-3 text-2xl font-bold text-slate-950">{{ $wallets->format($finance['settled_consumption']) }}</p>
                <p class="mt-2 text-xs text-slate-500">براساس تاریخ خدمت در این بازه</p>
            </a>
        @endadminRoute
    @endif
    @if ($activity['machines_count'] !== null)
        @adminRoute('admin.virtual-machines.index')
            <a href="{{ route('admin.virtual-machines.index', $reportRange) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                <h2 class="text-sm font-semibold text-slate-600">ماشین‌های جدید</h2>
                <p class="mt-3 text-2xl font-bold text-slate-950">{{ number_format($activity['machines_count']) }}</p>
                <p class="mt-2 text-xs text-slate-500">ایجادشده در این بازه · حذف‌شده‌ها محاسبه نمی‌شوند</p>
            </a>
        @endadminRoute
    @endif
    @if ($activity['customers_count'] !== null)
        @adminRoute('admin.customers.index')
            <a href="{{ route('admin.customers.index', $reportRange) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                <h2 class="text-sm font-semibold text-slate-600">مشتریان جدید</h2>
                <p class="mt-3 text-2xl font-bold text-slate-950">{{ number_format($activity['customers_count']) }}</p>
                <p class="mt-2 text-xs text-slate-500">ثبت‌نام در بازه انتخاب‌شده</p>
            </a>
        @endadminRoute
    @endif
</section>
