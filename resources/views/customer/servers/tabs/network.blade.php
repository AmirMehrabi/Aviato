<header class="mb-5">
    <h2 class="text-xl font-black text-slate-950">مصرف شبکه</h2>
    <p class="mt-1 text-sm text-slate-500">گزارش دوره، سهمیه و هزینه نهایی‌شده</p>
</header>

<section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6" aria-labelledby="daily-network-heading">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h3 id="daily-network-heading" class="text-lg font-black text-slate-950">نمودار ترافیک ورودی و خروجی</h3>
            <p class="mt-1 text-sm leading-6 text-slate-500">مصرف روزانه نهایی‌شده در ماه شمسی</p>
        </div>
        <form method="get" action="{{ route('customer.network.show', $server) }}" class="flex flex-wrap items-center gap-2 text-sm">
            <label for="network-month" class="font-bold text-slate-700">ماه مصرف</label>
            <select id="network-month" name="month" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-700">
                @foreach ($months as $value => $month)
                    <option value="{{ $value }}" @selected($selectedMonth === $value)>{{ \App\Support\PersianDigits::convert(\App\Support\Jalali::formatMonthYear($month->getYear(), $month->getMonth())) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-50 px-3 py-2 font-bold text-blue-700 hover:bg-blue-100">نمایش</button>
        </form>
    </div>

    @if ($dataState['partial'] || $dataState['missing'])
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">بازه‌های ناقص: {{ $dataState['partial'] }} · بازه‌های مفقود: {{ $dataState['missing'] }}</p>
    @endif

    @if ($daily->isNotEmpty())
        @php
            $series = $daily->map(fn ($day) => [
                'date' => $day->usage_date,
                'ingress' => (int) $day->ingress_bytes,
                'egress' => (int) $day->egress_bytes,
            ])->values();
        @endphp
        <div x-data="vmNetworkChart(@js($series), @js($chartStart), @js($chartEnd))" x-cloak class="relative mt-4 h-60 min-w-0 sm:h-72 xl:h-88" dir="ltr">
            <canvas x-ref="canvas" role="img" aria-label="نمودار مصرف روزانه ورودی و خروجی. مقادیر در جدول جزئیات مصرف روزانه آمده‌اند."></canvas>
        </div>

        <details class="mt-4 border-t border-slate-100 pt-2">
            <summary class="w-fit cursor-pointer rounded-md py-2 text-sm font-bold text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">جزئیات مصرف روزانه</summary>
            <table class="mt-2 w-full table-fixed text-right text-xs tabular-nums sm:text-sm">
                <caption class="sr-only">مقادیر روزانه ورودی و خروجی به گیگابایت</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="p-2 sm:px-4">روز</th>
                        <th scope="col" class="p-2 sm:px-4">ورودی (GB)</th>
                        <th scope="col" class="p-2 sm:px-4">خروجی (GB)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach ($daily as $day)
                        <tr>
                            <th scope="row" class="p-2 font-semibold sm:px-4"><x-network-usage-date :date="$day->usage_date" /></th>
                            <td class="break-all p-2 sm:px-4"><bdi dir="ltr">{{ $reports->gigabytes($day->ingress_bytes) }}</bdi></td>
                            <td class="break-all p-2 sm:px-4"><bdi dir="ltr">{{ $reports->gigabytes($day->egress_bytes) }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @else
        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">مصرف روزانه نهایی‌شده موجود نیست.</p>
    @endif
</section>

@if (! $summary['period'])
    <section class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <h3 class="font-black text-amber-900">هنوز دوره صورتحساب ثبت نشده است</h3>
        <p class="mt-2 text-sm leading-7 text-amber-800">پس از ثبت دوره، سهمیه و مبلغ اینجا نمایش داده می‌شود.</p>
    </section>
@else
    @php($billingPeriod = $summary['period'])
    <h3 class="mt-5 text-lg font-black text-slate-950">خلاصه صورتحساب شبکه</h3>
    <p class="mb-3 mt-1 text-sm text-slate-500">
        از <x-network-usage-date :date="$billingPeriod->period_start->copy()->setTimezone(config('app.customer_timezone'))->toDateString()" />
        تا <x-network-usage-date :date="$billingPeriod->period_end->copy()->subSecond()->setTimezone(config('app.customer_timezone'))->toDateString()" />
    </p>
    <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            'ورودی' => $reports->bytes($summary['ingress_bytes']),
            'خروجی' => $reports->bytes($summary['egress_bytes']),
            'قابل پرداخت' => $reports->bytes($summary['billable_bytes']),
        ] as $label => $value)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                <dd class="mt-2 break-words text-base font-black tabular-nums sm:text-xl" dir="ltr">{{ $value }}</dd>
            </div>
        @endforeach
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
            <dt class="text-xs text-slate-500">هزینه تخمینی دوره</dt>
            <dd class="mt-2 break-words text-base font-black text-blue-700 sm:text-xl">{{ $wallets->format($summary['accrued_amount']) }}</dd>
        </div>
    </dl>

    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
        <div class="flex flex-wrap justify-between gap-2">
            <div>
                <h3 class="font-black">سهمیه ماهانه</h3>
                <p class="mt-1 text-xs leading-6 text-slate-500">آخرین داده: {{ $summary['latest_bucket']?->interval_end?->diffForHumans() ?? 'نامشخص' }} · ساعات اخیر ممکن است هنوز نهایی نشده باشد.</p>
            </div>
            <b>{{ $summary['usage_percent'] === null ? 'بدون سهمیه' : $summary['usage_percent'].'٪' }}</b>
        </div>
        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full {{ ($summary['usage_percent'] ?? 100) >= 100 ? 'bg-amber-500' : 'bg-[#0069FF]' }}" style="width: {{ min(100, $summary['usage_percent'] ?? 100) }}%"></div>
        </div>
        <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
            <span>مصرف: {{ $reports->bytes($summary['rated_bytes']) }}</span>
            <span>باقی‌مانده: {{ $reports->bytes($summary['remaining_bytes']) }}</span>
            <span>سهمیه: {{ $reports->bytes($summary['included_bytes']) }}</span>
        </div>
    </section>
@endif
