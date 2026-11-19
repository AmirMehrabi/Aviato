@php
    $indicators = [
        [
            'label' => 'حافظه',
            'value' => $server->ram_gb.' GB',
            'icon' => 'M3 7h18v10H3zM7 10v3m5-3v3m5-3v3M6 17v3m4-3v3m4-3v3m4-3v3',
            'dir' => 'ltr',
        ],
        [
            'label' => 'پردازنده',
            'value' => $server->cpu_cores.' vCPU',
            'icon' => 'M6 6h12v12H6zM9 9h6v6H9zM9 3v3m6-3v3M9 18v3m6-3v3M3 9h3m-3 6h3m12-6h3m-3 6h3',
            'dir' => 'ltr',
        ],
        [
            'label' => 'دیسک اصلی',
            'value' => $server->disk_gb.' GB',
            'icon' => 'M4 13l2.5-7.5A2 2 0 0 1 8.4 4h7.2a2 2 0 0 1 1.9 1.5L20 13M4 13h16a1 1 0 0 1 1 1v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a1 1 0 0 1 1-1Zm3 3.5h.01m3.99 0h.01',
            'dir' => 'ltr',
        ],
        [
            'label' => 'هزینه این ماه',
            'value' => $canViewBilling ? $wallets->format($overviewUsage['cost']) : null,
            'unavailable' => 'نیازمند دسترسی مالی',
            'icon' => 'M19 8V6a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h13a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a3 3 0 0 1-3-3V7m18 6h-5a2 2 0 0 0 0 4h5',
            'dir' => 'rtl',
            'highlight' => true,
        ],
        [
            'label' => 'ترافیک خروجی',
            'value' => $overviewUsage['egress'],
            'icon' => 'M12 16V3m-5 5 5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4',
            'dir' => 'ltr',
        ],
        [
            'label' => 'ترافیک ورودی',
            'value' => $overviewUsage['ingress'],
            'icon' => 'M12 3v13m-5-5 5 5 5-5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4',
            'dir' => 'ltr',
        ],
    ];
@endphp

<section class="@container mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="server-overview-indicators">
    <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-slate-200 px-4 py-4 sm:px-5">
        <h2 id="server-overview-indicators" class="text-lg font-black text-slate-950">منابع و کارکرد سرور</h2>
        <p class="text-xs font-medium text-slate-600">هزینه و ترافیک ثبت‌شده از ابتدای ماه شمسی تا امروز</p>
    </header>

    <dl class="grid grid-cols-2 gap-px bg-slate-200 @min-[34rem]:grid-cols-3 @min-[66rem]:grid-cols-6">
        @foreach ($indicators as $indicator)
            <div class="min-w-0 p-3 sm:p-4 {{ ($indicator['highlight'] ?? false) ? 'bg-blue-50 text-blue-800' : 'bg-white text-slate-950' }}">
                <dt class="flex items-center gap-2 text-xs font-bold sm:text-sm">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg text-blue-700 {{ ($indicator['highlight'] ?? false) ? 'bg-white' : 'bg-blue-50' }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $indicator['icon'] }}" />
                        </svg>
                    </span>
                    {{ $indicator['label'] }}
                </dt>
                @if ($indicator['value'] !== null)
                    <dd class="mt-3 break-words text-xl font-black tabular-nums sm:text-2xl">
                        <bdi dir="{{ $indicator['dir'] }}">{{ $indicator['value'] }}</bdi>
                    </dd>
                @else
                    <dd class="mt-3 text-sm font-bold text-slate-600">{{ $indicator['unavailable'] ?? 'داده‌ای ثبت نشده' }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
</section>
