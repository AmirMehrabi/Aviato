@extends('layouts.admin')

@section('title', 'داشبورد مدیریت آویاتو')

@section('content')
    <div class="px-4 py-6 md:px-8 lg:px-10">

        {{-- SECTION 1: Status Strip --}}
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($statusStrip as $item)
                @php
                    $bgClass = match($item['tone']) { 'green' => 'bg-emerald-50', 'amber' => 'bg-amber-50', 'red' => 'bg-red-50', default => 'bg-slate-50' };
                    $dotClass = match($item['tone']) { 'green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', default => 'bg-slate-400' };
                @endphp
                <a href="{{ $item['url'] }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#B8D6FF] hover:shadow-md hover:shadow-[#0069FF]/10">
                    <span class="size-10 shrink-0 rounded-lg {{ $bgClass }} grid place-items-center">
                        <span class="size-3 rounded-full {{ $dotClass }}"></span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-slate-500">{{ $item['label'] }}</p>
                        <p class="text-lg font-black text-slate-950 leading-tight">{{ $item['value'] }}</p>
                        <p class="text-[11px] font-bold text-slate-400">{{ $item['sub'] }}</p>
                    </div>
                </a>
            @endforeach
        </section>

        {{-- SECTION 2: Charts Row --}}
        <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
            {{-- Revenue Chart --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">روند درآمد — ۳۰ روز اخیر</h2>
                    <span class="text-xs font-bold text-slate-400">{{ collect($revenueChart['labels'])->last() }}</span>
                </div>
                <div class="mt-4" style="height: 220px">
                    <canvas
                        x-data
                        x-init="
                            new Chart($el, {
                                type: 'line',
                                data: {
                                    labels: @js($revenueChart['labels']),
                                    datasets: [{
                                        label: 'درآمد',
                                        data: @js($revenueChart['data']),
                                        borderColor: '#0069FF',
                                        backgroundColor: (ctx) => {
                                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
                                            g.addColorStop(0, 'rgba(0,105,255,0.12)');
                                            g.addColorStop(1, 'rgba(0,105,255,0.01)');
                                            return g;
                                        },
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 0,
                                        pointHoverRadius: 5,
                                        pointHoverBackgroundColor: '#0069FF',
                                        borderWidth: 2,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            backgroundColor: '#031B4E',
                                            titleFont: { size: 11, weight: 'bold' },
                                            bodyFont: { size: 12, weight: '900' },
                                            padding: 10,
                                            cornerRadius: 8,
                                            callbacks: {
                                                label: (ctx) => ctx.parsed.y.toLocaleString('fa-IR') + ' تومان'
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            grid: { display: false },
                                            ticks: { font: { size: 10 }, maxTicksLimit: 7, color: '#94a3b8' }
                                        },
                                        y: {
                                            grid: { color: '#f1f5f9' },
                                            ticks: {
                                                font: { size: 10 },
                                                maxTicksLimit: 5,
                                                color: '#94a3b8',
                                                callback: (v) => v.toLocaleString('fa-IR')
                                            }
                                        }
                                    },
                                    interaction: { intersect: false, mode: 'index' },
                                }
                            })
                        "
                    ></canvas>
                </div>
            </div>

            {{-- Server Health --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">سلامت سرورها</h2>
                    <span class="rounded-md bg-[#EBF3FF] px-2 py-0.5 text-[11px] font-black text-[#0069FF]">{{ $health['proxmox_total'] }} سرور</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($serverHealth as $server)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate font-bold text-slate-800">{{ $server['name'] }}</span>
                                <span class="text-xs font-bold text-slate-500">{{ $server['value'] }}٪</span>
                            </div>
                            <p class="mt-0.5 truncate text-[11px] text-slate-400">{{ $server['detail'] }}</p>
                            <div class="mt-2 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full transition-all duration-500 {{ $server['color'] }}" style="width: {{ max(5, $server['value']) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center">
                            <p class="text-sm font-bold text-slate-400">سروری ثبت نشده</p>
                        </div>
                    @endforelse
                </div>
                <a href="{{ route('admin.proxmox-servers.index') }}" class="mt-5 inline-flex w-full justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                    مدیریت سرورها
                </a>
            </div>
        </section>

        {{-- SECTION 3: Operations Row --}}
        <section class="mt-6 grid gap-6 lg:grid-cols-2" id="operations-section">
            {{-- Critical Alerts --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">هشدارهای بحرانی</h2>
                    @if ($criticalAlerts->count() > 0)
                        <span class="rounded-md bg-red-50 px-2 py-0.5 text-[11px] font-black text-red-600">{{ $criticalAlerts->count() }} مورد</span>
                    @else
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-black text-emerald-600">همه چیز عادی</span>
                    @endif
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($criticalAlerts as $alert)
                        @php
                            $bgClass = match($alert['tone']) { 'red' => 'border-red-200 bg-red-50', 'amber' => 'border-amber-200 bg-amber-50', default => 'border-slate-200 bg-slate-50' };
                            $dotClass = match($alert['tone']) { 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', default => 'bg-slate-400' };
                        @endphp
                        <a href="{{ $alert['url'] }}" class="flex items-start gap-3 rounded-lg border p-3 transition hover:shadow-sm {{ $bgClass }}">
                            <span class="mt-1 size-2 shrink-0 rounded-full {{ $dotClass }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-black text-slate-900" dir="ltr">{{ $alert['title'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $alert['label'] }} — {{ $alert['meta'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-md bg-white px-2 py-0.5 text-[10px] font-black text-slate-600 shadow-sm">{{ $alert['action'] }}</span>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center">
                            <p class="text-sm font-bold text-slate-400">هشدار بحرانی وجود ندارد</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Open Tickets --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">تیکت‌های باز</h2>
                    <a href="{{ route('admin.tickets.index') }}" class="text-xs font-bold text-[#0069FF] hover:underline">مشاهده همه</a>
                </div>

                @php $totalQ = max(1, array_sum($ticketStats['by_priority'])); @endphp
                @if ($totalQ > 1)
                    <div class="mt-4 flex gap-1 overflow-hidden rounded-lg bg-slate-100 p-1">
                        @foreach (['urgent' => 'red', 'high' => 'amber', 'normal' => 'blue', 'low' => 'slate'] as $p => $c)
                            @if ($ticketStats['by_priority'][$p] > 0)
                                <div class="rounded-md px-2 py-1 text-[10px] font-black text-white {{ match($c) { 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'blue' => 'bg-[#0069FF]', default => 'bg-slate-400' } }}" style="flex: {{ $ticketStats['by_priority'][$p] }}">
                                    {{ $ticketStats['by_priority'][$p] }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 space-y-2">
                    @forelse ($recentTickets as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket['number']) }}" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 transition hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
                            <span class="size-8 shrink-0 rounded-lg grid place-items-center text-[10px] font-black {{ match($ticket['priority']) { 'urgent' => 'bg-red-50 text-red-600', 'high' => 'bg-amber-50 text-amber-600', 'normal' => 'bg-[#EBF3FF] text-[#0069FF]', default => 'bg-slate-100 text-slate-500' } }}">
                                {{ $ticket['number'] }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-900">{{ $ticket['subject'] }}</p>
                                <p class="text-[11px] text-slate-400">{{ $ticket['customer'] }} · {{ $ticket['time'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-black {{ match($ticket['status']) { 'open' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', default => 'bg-slate-100 text-slate-600' } }}">
                                {{ $ticket['status_label'] }}
                            </span>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center">
                            <p class="text-sm font-bold text-slate-400">تیکت بازی وجود ندارد</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- SECTION 4: Financial + Activity --}}
        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Financial Overview --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">نمای مالی</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-emerald-50 p-4">
                        <p class="text-[11px] font-bold text-emerald-600">درآمد امروز</p>
                        <p class="mt-1 text-xl font-black text-emerald-700">{{ $wallets->format($financial['today_revenue']) }}</p>
                    </div>
                    <div class="rounded-lg bg-[#EBF3FF] p-4">
                        <p class="text-[11px] font-bold text-[#0069FF]">درآمد ماه</p>
                        <p class="mt-1 text-xl font-black text-[#031B4E]">{{ $wallets->format($financial['month_revenue']) }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-4">
                        <p class="text-[11px] font-bold text-amber-600">بدهی کیف‌پول</p>
                        <p class="mt-1 text-xl font-black text-amber-700">{{ $financial['negative_wallets'] }} کیف پول</p>
                        <p class="text-[10px] text-amber-500">{{ $financial['negative_total'] }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-[11px] font-bold text-slate-500">فاکتور ماه</p>
                        <p class="mt-1 text-xl font-black text-slate-700">{{ $financial['month_invoices'] }}</p>
                        <p class="text-[10px] text-slate-400">{{ $wallets->format($financial['month_invoice_sum']) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('admin.customers.index') }}" class="flex-1 inline-flex justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        مشتریان
                    </a>
                    <a href="{{ route('admin.billing.rates.index') }}" class="flex-1 inline-flex justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        نرخ‌گذاری
                    </a>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">فعالیت‌های اخیر</h2>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-black text-slate-500">{{ $recentActivity->count() }} رویداد</span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($recentActivity as $activity)
                        @php $dot = match($activity['tone']) { 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', default => 'bg-[#0069FF]' }; @endphp
                        <div class="flex gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dot }}"></span>
                            <div class="min-w-0 flex-1">
                                @if ($activity['url'])
                                    <a href="{{ $activity['url'] }}" class="block truncate text-sm font-bold text-slate-900 transition hover:text-[#0069FF]">{{ $activity['title'] }}</a>
                                @else
                                    <p class="truncate text-sm font-bold text-slate-900">{{ $activity['title'] }}</p>
                                @endif
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ $activity['meta'] }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ $activity['time']?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">فعالیتی ثبت نشده.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
