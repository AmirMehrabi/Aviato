@extends('customer.layout')

@section('title', 'ساخت ماشین')
@section('header_title', 'ساخت ماشین')
@section('header_subtitle', 'انتخاب پلن، سیستم عامل و دیتاسنتر برای VPS جدید')

@php
    $activeNav = 'servers';
@endphp

@section('search_data')
[
    {
        "title": "سرورها",
        "description": "بازگشت به فهرست ماشین ها",
        "type": "صفحه",
        "url": @json(route('customer.servers.index', [], false)),
        "keywords": "servers ماشین سرورها"
    }
]
@endsection

@section('content')
    @php
        $fallbackPlans = collect([
            ['name' => 'Starter', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 60, 'ip_count' => 1, 'price' => '۷۹۰٬۰۰۰', 'description' => 'شروع مناسب برای سایت و API سبک'],
            ['name' => 'Growth', 'cpu_cores' => 4, 'ram_gb' => 8, 'disk_gb' => 120, 'ip_count' => 1, 'price' => '۱٬۴۹۰٬۰۰۰', 'description' => 'مناسب فروشگاه و سرویس های پرترافیک'],
            ['name' => 'Performance', 'cpu_cores' => 8, 'ram_gb' => 16, 'disk_gb' => 240, 'ip_count' => 1, 'price' => '۲٬۹۰۰٬۰۰۰', 'description' => 'برای دیتابیس و پردازش سنگین'],
        ]);
        $planCards = $bundles->isNotEmpty()
            ? $bundles->map(fn ($bundle) => [
                'name' => $bundle->name,
                'cpu_cores' => $bundle->cpu_cores,
                'ram_gb' => $bundle->ram_gb,
                'disk_gb' => $bundle->disk_gb,
                'ip_count' => $bundle->ip_count,
                'price' => $wallets->format($bundle->monthly_price),
                'description' => $bundle->description ?: 'باندل آماده برای ساخت سریع VPS',
            ])
            : $fallbackPlans;
        $images = ['Ubuntu 24.04 LTS', 'Debian 12', 'Rocky Linux 9', 'Windows Server 2022'];
        $regions = ['تهران ۱', 'شیراز ۱', 'فرانکفورت'];
    @endphp

    <section
        x-data="{
            selectedPlan: 0,
            selectedImage: 0,
            selectedRegion: 0,
            plans: @js($planCards->values()),
            images: @js($images),
            regions: @js($regions)
        }"
        class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        <div class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#0069FF]">Step 1</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">پلن VPS را انتخاب کنید</h2>
                    </div>
                    <span class="rounded-md bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">نمایشی تا اتصال provisioning</span>
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    @foreach ($planCards as $index => $plan)
                        <button
                            type="button"
                            @click="selectedPlan = {{ $index }}"
                            class="rounded-xl border p-4 text-right transition"
                            :class="selectedPlan === {{ $index }} ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:bg-slate-50'"
                        >
                            <span class="block font-black text-slate-950">{{ $plan['name'] }}</span>
                            <span class="mt-2 block min-h-10 text-xs leading-6 text-slate-500">{{ $plan['description'] }}</span>
                            <span class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b>{{ $plan['cpu_cores'] }}</b><br>CPU</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b>{{ $plan['ram_gb'] }}</b><br>RAM</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b>{{ $plan['disk_gb'] }}</b><br>Disk</span>
                            </span>
                            <span class="mt-4 block text-left text-lg font-black text-slate-950">{{ $plan['price'] }} <small class="text-xs text-slate-500">/ ماه</small></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <p class="text-sm font-black text-[#0069FF]">Step 2</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">سیستم عامل</h2>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ($images as $index => $image)
                            <button
                                type="button"
                                @click="selectedImage = {{ $index }}"
                                class="rounded-lg border px-4 py-3 text-right text-sm font-black transition"
                                :class="selectedImage === {{ $index }} ? 'border-[#0069FF] bg-[#F2F8FF] text-[#0069FF]' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                {{ $image }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <p class="text-sm font-black text-[#0069FF]">Step 3</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">دیتاسنتر</h2>
                    <div class="mt-5 grid gap-3">
                        @foreach ($regions as $index => $region)
                            <button
                                type="button"
                                @click="selectedRegion = {{ $index }}"
                                class="flex items-center justify-between rounded-lg border px-4 py-3 text-right text-sm font-black transition"
                                :class="selectedRegion === {{ $index }} ? 'border-[#0069FF] bg-[#F2F8FF] text-[#0069FF]' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                <span>{{ $region }}</span>
                                <span class="text-xs text-slate-400">آماده</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="sticky top-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">خلاصه ساخت</h2>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="font-bold text-slate-500">پلن</span>
                        <span class="font-black text-slate-950" x-text="plans[selectedPlan]?.name"></span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="font-bold text-slate-500">سیستم عامل</span>
                        <span class="font-black text-slate-950" x-text="images[selectedImage]"></span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="font-bold text-slate-500">دیتاسنتر</span>
                        <span class="font-black text-slate-950" x-text="regions[selectedRegion]"></span>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">هزینه ماهانه تقریبی</p>
                        <p class="mt-2 text-2xl font-black text-slate-950" x-text="plans[selectedPlan]?.price"></p>
                    </div>
                </div>
                <button type="button" class="mt-5 w-full rounded-lg bg-slate-300 px-4 py-3 text-sm font-black text-slate-600" disabled>
                    ثبت درخواست ساخت (به زودی)
                </button>
                <p class="mt-3 text-xs leading-6 text-slate-500">این صفحه فعلا مسیر خرید را نمایش می دهد و بعدا به provisioning واقعی متصل می شود.</p>
            </div>
        </aside>
    </section>
@endsection
