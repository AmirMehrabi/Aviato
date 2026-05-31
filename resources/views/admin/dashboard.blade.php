@extends('layouts.admin')

@section('title', 'داشبورد مدیریت آویاتو')

@section('content')
    <div class="px-4 py-6 md:px-8 lg:px-10">
        <section class="overflow-hidden rounded-lg border border-[#B8D6FF] bg-[#031B4E] text-white shadow-sm">
            <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6">
                <div>
                    <p class="text-xs font-black uppercase tracking-normal text-[#B8D6FF]">Aviato Operations</p>
                    <h1 class="mt-3 text-2xl font-black tracking-normal md:text-3xl">داشبورد عملیات زیرساخت</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-white/70">
                        داده‌های واقعی VM، Proxmox، کیف پول، پرداخت، بکاپ و درخواست‌های مشتری در یک نمای اجرایی.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('admin.virtual-machines.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm shadow-[#0069FF]/30 transition hover:bg-[#0050D0]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            ساخت VM
                        </a>
                        <a href="{{ route('admin.proxmox-servers.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-black text-white transition hover:bg-white/15">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4v6h6M20 20v-6h-6M20 9A7 7 0 0 0 8.2 4.2L4 10M4 15a7 7 0 0 0 11.8 4.8L20 14" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Sync زیرساخت
                        </a>
                    </div>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/10 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-white/70">آمادگی عملیاتی</span>
                        <span class="rounded-md bg-white px-2.5 py-1 text-xs font-black text-[#031B4E]">{{ $health['ready_score'] }}٪</span>
                    </div>
                    <div class="mt-4 h-2 rounded-full bg-white/15">
                        <div class="h-2 rounded-full bg-[#B8D6FF]" style="width: {{ $health['ready_score'] }}%"></div>
                    </div>
                    <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-white/10 p-3">
                            <p class="text-xl font-black">{{ $health['proxmox_online'] }}</p>
                            <p class="mt-1 text-[11px] font-bold text-white/60">آنلاین</p>
                        </div>
                        <div class="rounded-lg bg-white/10 p-3">
                            <p class="text-xl font-black">{{ $health['pending_sync'] }}</p>
                            <p class="mt-1 text-[11px] font-bold text-white/60">Sync</p>
                        </div>
                        <div class="rounded-lg bg-white/10 p-3">
                            <p class="text-xl font-black">{{ $health['failed_backups'] }}</p>
                            <p class="mt-1 text-[11px] font-bold text-white/60">Backup</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($operationStats as $stat)
                @php
                    $dotClass = $stat['tone'] === 'text-red-600' ? 'bg-red-500' : ($stat['tone'] === 'text-amber-600' ? 'bg-amber-500' : 'bg-[#0069FF]');
                    $barClass = $stat['tone'] === 'text-red-600' ? 'bg-red-500' : ($stat['tone'] === 'text-amber-600' ? 'bg-amber-500' : 'bg-[#0069FF]');
                @endphp
                <a href="{{ $stat['url'] }}" class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#B8D6FF] hover:shadow-md hover:shadow-[#0069FF]/10">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-black text-slate-500">{{ $stat['label'] }}</p>
                        <span class="size-2.5 rounded-full {{ $dotClass }}"></span>
                    </div>
                    <p class="mt-3 truncate text-2xl font-black {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                    <p class="mt-2 min-h-10 text-xs leading-5 text-slate-500">{{ $stat['detail'] }}</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $barClass }}" style="width: {{ max(6, $stat['bar']) }}%"></div>
                    </div>
                </a>
            @endforeach
        </section>

        <section class="mt-6 grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">صف اقدام فوری</h2>
                        <p class="mt-1 text-sm text-slate-500">اولویت‌بندی خطاها و ریسک‌هایی که باید از همین صفحه قابل پیگیری باشند.</p>
                    </div>
                    <a href="{{ route('admin.virtual-machines.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        همه ماشین‌ها
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-right text-sm">
                        <thead class="bg-slate-50 text-xs font-black text-slate-500">
                            <tr>
                                <th class="px-5 py-4">موضوع</th>
                                <th class="px-5 py-4">نوع</th>
                                <th class="px-5 py-4">جزئیات</th>
                                <th class="px-5 py-4">اولویت</th>
                                <th class="px-5 py-4">اقدام</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($attentionItems as $item)
                                @php
                                    $toneClass = match ($item['tone']) {
                                        'red' => 'bg-red-50 text-red-700 ring-red-100',
                                        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
                                        'blue' => 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]',
                                        default => 'bg-slate-100 text-slate-700 ring-slate-200',
                                    };
                                    $dotClass = match ($item['tone']) {
                                        'red' => 'bg-red-500',
                                        'amber' => 'bg-amber-500',
                                        'blue' => 'bg-[#0069FF]',
                                        default => 'bg-slate-400',
                                    };
                                @endphp
                                <tr class="transition hover:bg-[#F8FBFF]">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <a href="{{ $item['url'] }}" class="block font-black text-slate-950 transition hover:text-[#0069FF]" dir="ltr">{{ $item['title'] }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black ring-1 {{ $toneClass }}">
                                            <span class="size-2 rounded-full {{ $dotClass }}"></span>
                                            {{ $item['label'] }}
                                        </span>
                                    </td>
                                    <td class="min-w-64 px-5 py-4 text-slate-600">{{ $item['meta'] }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-black text-slate-500">{{ $item['priority'] }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <a href="{{ $item['url'] }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-[#0069FF] transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF]">{{ $item['action'] }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm font-bold text-slate-500">فعلا مورد فوری برای پیگیری وجود ندارد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="min-w-0 space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-black text-slate-950">سلامت Proxmox</h2>
                        <span class="rounded-md bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">{{ $health['proxmox_total'] }} سرور</span>
                    </div>
                    <div class="mt-5 space-y-4">
                        @foreach ($capacityRows as $capacity)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-black text-slate-800">{{ $capacity['name'] }}</span>
                                    <span class="font-bold text-slate-500">{{ $capacity['value'] }}٪</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $capacity['detail'] }}</p>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full {{ $capacity['color'] }}" style="width: {{ max(5, $capacity['value']) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-black text-slate-950">ریسک مالی</h2>
                    <div class="mt-5 space-y-3">
                        @foreach ($financialRisk as $risk)
                            @php
                                $riskClass = match ($risk['tone']) {
                                    'red' => 'border-red-100 bg-red-50 text-red-800',
                                    'amber' => 'border-amber-100 bg-amber-50 text-amber-800',
                                    default => 'border-[#B8D6FF] bg-[#EBF3FF] text-[#031B4E]',
                                };
                            @endphp
                            <a href="{{ $risk['url'] }}" class="block rounded-lg border p-3 transition hover:shadow-sm {{ $riskClass }}">
                                <p class="text-sm font-black">{{ $risk['title'] }}</p>
                                <p class="mt-1 text-xs leading-6 opacity-80">{{ $risk['body'] }}</p>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.customers.index') }}" class="mt-4 inline-flex w-full justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        بررسی مشتریان
                    </a>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">ظرفیت و موجودی</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['label' => 'کل VM', 'value' => $inventory['vms_total'], 'hint' => $inventory['vms_running'].' روشن'],
                        ['label' => 'مشتریان فعال', 'value' => $inventory['active_customers'], 'hint' => $inventory['suspended_customers'].' تعلیق'],
                        ['label' => 'Cloud Image فعال', 'value' => $inventory['cloud_images'], 'hint' => 'برای ساخت سریع'],
                        ['label' => 'باندل فعال', 'value' => $inventory['active_bundles'], 'hint' => 'قیمت‌گذاری آماده'],
                    ] as $item)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-black text-slate-500">{{ $item['label'] }}</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $item['value'] }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $item['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('admin.cloud-images.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">Cloud Images</a>
                    <a href="{{ route('admin.ip-pools.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">IP Pools</a>
                    <a href="{{ route('admin.billing.bundles.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">Bundles</a>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-black text-slate-950">فعالیت‌های اخیر</h2>
                    <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">{{ $recentActivity->count() }} رویداد</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($recentActivity as $activity)
                        @php
                            $activityDot = match ($activity['tone']) {
                                'red' => 'bg-red-500',
                                'amber' => 'bg-amber-500',
                                default => 'bg-[#0069FF]',
                            };
                        @endphp
                        <div class="flex gap-3">
                            <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $activityDot }}"></span>
                            <div class="min-w-0 flex-1">
                                @if ($activity['url'])
                                    <a href="{{ $activity['url'] }}" class="block truncate text-sm font-black text-slate-900 transition hover:text-[#0069FF]">{{ $activity['title'] }}</a>
                                @else
                                    <p class="truncate text-sm font-black text-slate-900">{{ $activity['title'] }}</p>
                                @endif
                                <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $activity['meta'] }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-bold text-slate-400">{{ $activity['time']?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">هنوز فعالیتی برای نمایش وجود ندارد.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
