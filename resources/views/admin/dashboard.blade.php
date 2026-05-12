@extends('layouts.admin')

@section('title', 'داشبورد مدیریت آویاتو')

@section('content')
            <div class="px-4 py-6 md:px-8 lg:px-10">
                <section class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-2xl font-black tracking-normal text-slate-950 md:text-3xl">داشبورد عملیات VM</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600">
                            وضعیت فروش، ساخت، ظرفیت Proxmox و ریسک‌های مالی مشتریان را از یک نمای مدیریتی پیگیری کنید.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.virtual-machines.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-[#0050D0]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            ساخت VM
                        </a>
                        <a href="{{ route('admin.proxmox-servers.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                            Sync زیرساخت
                        </a>
                    </div>
                </section>

                <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    @php
                        $stats = [
                            ['label' => 'VM فعال', 'value' => '۱۲۸', 'change' => '۹ ماشین بیشتر از هفته قبل', 'tone' => 'text-[#0050D0]', 'bar' => '82'],
                            ['label' => 'صف Provisioning', 'value' => '۷', 'change' => '۲ مورد بیشتر از SLA', 'tone' => 'text-amber-600', 'bar' => '48'],
                            ['label' => 'فاکتورهای پرریسک', 'value' => '۱۸', 'change' => '۴ مشتری نزدیک تعلیق', 'tone' => 'text-red-600', 'bar' => '36'],
                            ['label' => 'هشدار زیرساخت', 'value' => '۳', 'change' => 'تهران ۱، بکاپ، Sync', 'tone' => 'text-amber-600', 'bar' => '28'],
                            ['label' => 'درآمد امروز', 'value' => '۲.۸M', 'change' => 'تومان تا این لحظه', 'tone' => 'text-slate-950', 'bar' => '64'],
                        ];
                    @endphp
                    @foreach ($stats as $stat)
                        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-black text-slate-500">{{ $stat['label'] }}</p>
                                <span class="size-2.5 rounded-full {{ $stat['tone'] === 'text-red-600' ? 'bg-red-500' : ($stat['tone'] === 'text-amber-600' ? 'bg-amber-500' : 'bg-blue-500') }}"></span>
                            </div>
                            <p class="mt-3 text-2xl font-black {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                            <p class="mt-2 min-h-10 text-xs leading-5 text-slate-500">{{ $stat['change'] }}</p>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#0069FF]" style="width: {{ $stat['bar'] }}%"></div>
                            </div>
                        </article>
                    @endforeach
                </section>

                <section class="mt-6 grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-lg font-black text-slate-950">ماشین‌های نیازمند توجه</h2>
                                <p class="mt-1 text-sm text-slate-500">اولویت‌بندی بر اساس SLA ساخت، هزینه، ظرفیت و ریسک مشتری</p>
                            </div>
                            <div class="flex rounded-lg bg-slate-100 p-1 text-sm font-bold">
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'روزانه' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'روزانه'">روزانه</button>
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'هفتگی' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'هفتگی'">هفتگی</button>
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'ماهانه' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'ماهانه'">ماهانه</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-right text-sm">
                                <thead class="bg-slate-50 text-xs font-black text-slate-500">
                                    <tr>
                                        <th class="px-5 py-4">ماشین</th>
                                        <th class="px-5 py-4">مشتری</th>
                                        <th class="px-5 py-4">نود</th>
                                        <th class="px-5 py-4">منابع</th>
                                        <th class="px-5 py-4">وضعیت</th>
                                        <th class="px-5 py-4">هزینه</th>
                                        <th class="px-5 py-4">اقدام</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @php
                                        $machines = [
                                            ['name' => 'staging-api', 'ip' => '49.13.88.104', 'customer' => 'رها پرداز', 'node' => 'fra-node-02', 'plan' => '۱ vCPU / ۲GB', 'status' => 'Provisioning', 'statusClass' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500', 'cost' => '۱۸۶٬۰۰۰', 'action' => 'بررسی صف'],
                                            ['name' => 'db-main', 'ip' => '185.143.232.41', 'customer' => 'نوآوران شرق', 'node' => 'thr-node-01', 'plan' => '۴ vCPU / ۸GB', 'status' => 'مصرف بالا', 'statusClass' => 'bg-red-50 text-red-700', 'dot' => 'bg-red-500', 'cost' => '۱٬۲۴۰٬۰۰۰', 'action' => 'مشاهده'],
                                            ['name' => 'web-prod-01', 'ip' => '185.143.232.18', 'customer' => 'آریا تجارت', 'node' => 'shr-node-01', 'plan' => '۲ vCPU / ۴GB', 'status' => 'روشن', 'statusClass' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500', 'cost' => '۴۹۰٬۰۰۰', 'action' => 'مدیریت'],
                                            ['name' => 'backup-worker', 'ip' => '185.143.233.77', 'customer' => 'داده‌یار', 'node' => 'thr-node-03', 'plan' => '۲ vCPU / ۸GB', 'status' => 'بکاپ ناموفق', 'statusClass' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500', 'cost' => '۶۸۰٬۰۰۰', 'action' => 'Retry'],
                                        ];
                                    @endphp
                                    @foreach ($machines as $machine)
                                        <tr class="transition hover:bg-blue-50/40">
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span class="block font-black text-slate-950" dir="ltr">{{ $machine['name'] }}</span>
                                                <span class="mt-1 block font-mono text-xs text-slate-500" dir="ltr">{{ $machine['ip'] }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 font-bold text-slate-700">{{ $machine['customer'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{{ $machine['node'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $machine['plan'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black {{ $machine['statusClass'] }}">
                                                    <span class="size-2 rounded-full {{ $machine['dot'] }}"></span>
                                                    {{ $machine['status'] }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 font-black text-slate-900">{{ $machine['cost'] }} <span class="text-xs font-bold text-slate-400">تومان</span></td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-[#0050D0] transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF]">{{ $machine['action'] }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="min-w-0 space-y-6">
                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h2 class="font-black text-slate-950">ظرفیت دیتاسنتر</h2>
                                <span class="text-xs font-black text-slate-400" x-text="period"></span>
                            </div>
                            <div class="mt-5 space-y-4">
                                @foreach ([['name' => 'تهران ۱', 'value' => 87, 'color' => 'bg-amber-500'], ['name' => 'شیراز ۱', 'value' => 54, 'color' => 'bg-[#0069FF]'], ['name' => 'فرانکفورت', 'value' => 41, 'color' => 'bg-[#0069FF]']] as $capacity)
                                    <div>
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-black text-slate-800">{{ $capacity['name'] }}</span>
                                            <span class="font-bold text-slate-500">{{ $capacity['value'] }}٪</span>
                                        </div>
                                        <div class="mt-2 h-2 rounded-full bg-slate-100">
                                            <div class="h-2 rounded-full {{ $capacity['color'] }}" style="width: {{ $capacity['value'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black text-slate-950">ریسک مالی مشتریان</h2>
                            <div class="mt-5 space-y-3">
                                <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                                    <p class="text-sm font-black text-red-800">۲ VM در آستانه توقف خودکار</p>
                                    <p class="mt-1 text-xs leading-6 text-red-700/80">کیف پول کمتر از هزینه ۱۲ ساعت آینده است.</p>
                                </div>
                                <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
                                    <p class="text-sm font-black text-amber-800">۵ فاکتور پرداخت‌نشده</p>
                                    <p class="mt-1 text-xs leading-6 text-amber-700/80">برای مشتریان سازمانی پیگیری دستی لازم است.</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.customers.index') }}" class="mt-4 inline-flex w-full justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                                بررسی مشتریان
                            </a>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black text-slate-950">فعالیت‌های اخیر</h2>
                            <div class="mt-5 space-y-4">
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-[#0069FF]"></span>
                                    <p class="text-sm leading-7 text-slate-600"><span class="font-black text-slate-900">thr-node-03</span> با موفقیت Sync شد.</p>
                                </div>
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-amber-500"></span>
                                    <p class="text-sm leading-7 text-slate-600">Provisioning برای <span class="font-black text-slate-900">staging-api</span> از SLA عبور کرد.</p>
                                </div>
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-red-500"></span>
                                    <p class="text-sm leading-7 text-slate-600">هشدار CPU روی <span class="font-black text-slate-900">db-main</span> ثبت شد.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
@endsection
