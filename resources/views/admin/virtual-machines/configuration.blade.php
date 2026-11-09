@php
    $observed = $vm->hardware_observation;
    $priceInputCurrency = \App\Models\AppSetting::currency() === 'IRR' ? 'ریال' : \App\Models\AppSetting::currency();
    $hardwareSupported = $vm->isProxmox() && ! $vm->isLxc() && $vm->proxmox_server_id && $vm->vmid && $vm->node;
    $configurationLocked = $vm->isActionLocked() || $vm->provisioning_status !== 'ready' || $vm->status === 'suspended';
    $drift = $observed && ($observed['cpu_cores'] !== $vm->cpu_cores || $observed['ram_mb'] !== $vm->ram_gb * 1024 || $observed['disk_bytes'] !== $vm->disk_gb * 1024 ** 3);
    $modeLabels = ['inherit' => 'پیروی از پلن', 'custom' => 'قیمت اختصاصی', 'resources' => 'نرخ منابع'];
@endphp
<section id="configuration" class="mt-6 scroll-mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-5 md:p-6">
        <div>
            <p class="text-xs font-bold text-[#0069FF]">مدیریت این ماشین</p>
            <h2 class="mt-1 text-xl font-black text-slate-950" x-text="tab === 'billing' ? 'قیمت‌گذاری' : 'منابع و تنظیمات'">منابع و تنظیمات</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">ابتدا تغییر را انتخاب کنید؛ پیش از ثبت، منابع و هزینه نهایی را مرور خواهید کرد.</p>
        </div>
        <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-800">{{ $modeLabels[$vm->pricing_mode ?? 'inherit'] }}</span>
    </div>
    @if ($errors->any())
        <div role="alert" class="mx-5 mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-bold">تغییرات هنوز ثبت نشده است</p>
            <ul class="mt-2 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($configurationLocked)
        <p role="status" class="mx-5 mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">تغییر تنظیمات پس از پایان عملیات جاری و آماده شدن سرور در دسترس خواهد بود. بررسی سخت‌افزار همچنان امکان‌پذیر است.</p>
    @endif
    <div class="grid divide-y divide-slate-100">
        <div x-show="tab === 'configuration'" class="p-5 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-black text-slate-950">سخت‌افزار Proxmox</h3>
                @if ($hardwareSupported)
                <form method="POST" action="{{ route('admin.virtual-machines.configuration.refresh', $vm) }}" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <button :disabled="busy" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-[#0069FF] hover:bg-blue-50 disabled:opacity-50" x-text="busy ? 'در حال بررسی…' : 'بررسی مجدد'">بررسی مجدد</button>
                </form>
                @endif
            </div>
            @if ($hardwareSupported)
                <p class="mt-2 text-xs leading-6 text-slate-500">{{ $vm->hardware_checked_at ? 'آخرین بررسی موفق: '.$vm->hardware_checked_at->diffForHumans() : 'برای دیدن منابع واقعی، اولین بررسی را انجام دهید.' }} بررسی به‌تنهایی قیمت یا تنظیمات پنل را تغییر نمی‌دهد.</p>
                @if ($vm->hardware_sync_error)<p role="alert" class="mt-3 rounded-lg bg-red-50 p-3 text-xs leading-6 text-red-800">{{ $vm->hardware_sync_error }}</p>@endif
                @if ($observed)
                    <p class="mt-4 rounded-lg px-3 py-2 text-xs font-bold {{ $drift ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800' }}">{{ $drift ? 'منابع واقعی با اطلاعات پنل متفاوت است' : 'منابع واقعی و پنل یکسان است' }}</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="text-xs text-slate-500"><tr><th class="py-3 font-medium">منبع</th><th>پنل</th><th>فعال در Proxmox</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr><th class="py-3 font-medium">پردازنده</th><td>{{ $vm->cpu_cores }} vCPU</td><td>{{ $observed['cpu_cores'] }} vCPU</td></tr>
                                <tr><th class="py-3 font-medium">حافظه</th><td>{{ $vm->ram_gb }} GiB</td><td>{{ round($observed['ram_mb'] / 1024, 3) }} GiB</td></tr>
                                <tr><th class="py-3 font-medium">دیسک اصلی</th><td>{{ $vm->disk_gb }} GiB</td><td>{{ round($observed['disk_bytes'] / 1024 ** 3, 3) }} GiB <span class="text-xs text-slate-400">{{ $observed['primary_device'] }}</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    @if (! empty($observed['pending']))
                        <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs leading-6 text-amber-900"><p class="font-bold">تغییرات در انتظار اعمال</p><p>ابتدا تغییرات زیر را در Proxmox اعمال یا لغو کنید؛ سپس دوباره بررسی کنید.</p>
                            @foreach ($observed['pending'] as $pending)<p dir="ltr">{{ $pending['key'] ?? '—' }}: {{ is_scalar($pending['pending'] ?? null) ? $pending['pending'] : 'حذف / تغییر' }}</p>@endforeach
                        </div>
                    @endif
                    @if (count($observed['disks']) > 1)
                        <details class="mt-3 text-xs text-slate-600"><summary class="cursor-pointer py-2 font-bold">نمایش سایر دیسک‌ها</summary>
                            @foreach ($observed['disks'] as $device => $disk)
                                @if ($device !== $observed['primary_device'])<p class="py-1" dir="ltr">{{ $device }} · {{ $disk['size_bytes'] === null ? 'نامشخص' : round($disk['size_bytes'] / 1024 ** 3, 3).' GiB' }}</p>@endif
                            @endforeach
                            <p class="mt-2 leading-6">همگام‌سازی فقط دیسک اصلی را می‌پذیرد. دیسک‌های اضافه و هزینه آن‌ها باید جداگانه بررسی شوند.</p>
                        </details>
                    @endif
                    @adminAbility('virtual-machines.manage')
                    @if ($drift && empty($observed['pending']))
                        <form method="POST" action="{{ route('admin.virtual-machines.configuration.preview', $vm) }}" class="mt-4 space-y-3" x-data="{ busy: false }" @submit="busy = true">
                            @csrf <input type="hidden" name="kind" value="sync">
                            <label for="sync-reason" class="block text-xs font-bold text-slate-600">دلیل پذیرش تغییرات</label>
                            <input id="sync-reason" name="reason" required minlength="3" maxlength="1000" value="{{ old('kind') === 'sync' ? old('reason') : '' }}" placeholder="مثلاً: افزایش منابع توسط تیم زیرساخت" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            <button @disabled($configurationLocked) :disabled="busy" class="rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" x-text="busy ? 'در حال آماده‌سازی…' : 'مرور و پذیرش سخت‌افزار'">مرور و پذیرش سخت‌افزار</button>
                        </form>
                    @endif
                    @endadminAbility
                @endif
                @adminAbility('virtual-machines.manage')
                @adminAbility('virtual-machines.power')
                <details class="mt-5 border-t border-slate-100 pt-4" @if(old('kind') === 'hardware') open @endif>
                    <summary class="cursor-pointer text-sm font-bold text-[#0069FF]">تغییر مستقل پردازنده، حافظه و دیسک</summary>
                    <form method="POST" action="{{ route('admin.virtual-machines.configuration.preview', $vm) }}" class="mt-4 space-y-4" x-data="{ busy: false }" @submit="busy = true">
                        @csrf <input type="hidden" name="kind" value="hardware">
                        <fieldset @disabled($configurationLocked) class="space-y-4 disabled:opacity-50">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach (['cpu_cores' => ['پردازنده (vCPU)', $vm->cpu_cores, 1, 512], 'ram_gb' => ['حافظه (GiB)', $vm->ram_gb, 1, 1048576], 'disk_gb' => ['دیسک اصلی (GiB)', $vm->disk_gb, $vm->disk_gb, 1048576]] as $field => $spec)
                                <div><label for="hardware-{{ $field }}" class="mb-2 block text-xs font-bold text-slate-600">{{ $spec[0] }}</label><input id="hardware-{{ $field }}" name="{{ $field }}" type="number" required min="{{ $spec[2] }}" max="{{ $spec[3] }}" value="{{ old('kind') === 'hardware' ? old($field, $spec[1]) : $spec[1] }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" dir="ltr"></div>
                                @endforeach
                            </div>
                            <p class="rounded-xl bg-slate-50 p-3 text-xs leading-6 text-slate-600">پردازنده و حافظه قابل افزایش یا کاهش است؛ دیسک فقط افزایش می‌یابد. سرور روشن، با خاموشی کنترل‌شده تغییر می‌کند و سرور خاموش، خاموش می‌ماند. افزایش دیسک ممکن است نیازمند گسترش پارتیشن داخل سیستم‌عامل باشد.</p>
                            <label for="hardware-reason" class="block text-xs font-bold text-slate-600">دلیل تغییر</label>
                            <input id="hardware-reason" name="reason" required minlength="3" maxlength="1000" value="{{ old('kind') === 'hardware' ? old('reason') : '' }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            <button :disabled="busy" class="rounded-xl border border-[#0069FF] px-4 py-2.5 text-sm font-bold text-[#0069FF] disabled:opacity-50" x-text="busy ? 'در حال آماده‌سازی…' : 'مرور تغییرات سخت‌افزار'">مرور تغییرات سخت‌افزار</button>
                        </fieldset>
                    </form>
                </details>
                @endadminAbility
                @endadminAbility
            @else
                <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-500">مدیریت مستقل سخت‌افزار برای ماشین QEMU متصل به Proxmox در دسترس است. تغییرات سایر ارائه‌دهندگان از مسیر ارتقای پلن انجام می‌شود.</p>
            @endif
        </div>
        <div x-show="tab === 'billing'" class="p-5 md:p-6">
            <h3 class="font-black text-slate-950">قیمت همین ماشین</h3>
            @adminAbility('billing.read')
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-blue-50 p-4"><p class="text-xs text-slate-500">ماهانه معادل · روشن</p><p class="mt-2 text-lg font-black text-[#031B4E]">{{ $money->format((int) round($billing->hourlyWhenRunning($vm) * 730)) }}</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">ماهانه معادل · خاموش</p><p class="mt-2 text-lg font-black text-[#031B4E]">{{ $money->format((int) round($billing->persistentHourly($vm) * 730)) }}</p></div>
            </div>
            @endadminAbility
            <p class="mt-3 text-xs leading-6 text-slate-500">مبنای محاسبه ۷۳۰ ساعت است؛ صورتحساب بر اساس زمان واقعی استفاده صادر می‌شود. بکاپ، دیسک اضافه و مازاد ترافیک جداگانه محاسبه می‌شوند.</p>
            @adminAbility('pricing.manage')
            <form method="POST" action="{{ route('admin.virtual-machines.configuration.preview', $vm) }}" class="mt-5 space-y-4" x-data="{ mode: @js(old('kind') === 'pricing' ? old('pricing_mode', $vm->pricing_mode ?? 'inherit') : ($vm->pricing_mode ?? 'inherit')), busy: false }" @submit="busy = true">
                @csrf <input type="hidden" name="kind" value="pricing">
                <fieldset @disabled($configurationLocked) class="space-y-4 disabled:opacity-50">
                    <legend class="mb-3 text-xs font-bold text-slate-600">روش قیمت‌گذاری</legend>
                    <div class="space-y-2">
                        @foreach (['inherit' => ['پیروی از پلن', 'قیمت با تغییر نرخ پلن به‌روز می‌شود.'], 'custom' => ['قیمت اختصاصی ثابت', 'فقط برای این ماشین؛ با تغییر منابع حفظ می‌شود.'], 'resources' => ['بر اساس منابع', 'هزینه بر اساس منابع پذیرفته‌شده و نرخ‌های فعال محاسبه می‌شود.']] as $mode => $copy)
                        @if ($mode !== 'resources' || $vm->isProxmox())
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="mode === '{{ $mode }}' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" name="pricing_mode" value="{{ $mode }}" x-model="mode" @checked(($vm->pricing_mode ?? 'inherit') === $mode) class="mt-1">
                            <span><span class="block text-sm font-bold text-slate-900">{{ $copy[0] }}</span><span class="mt-1 block text-xs leading-6 text-slate-500">{{ $copy[1] }}</span></span>
                        </label>
                        @endif
                        @endforeach
                    </div>
                    <div x-show="mode === 'custom'" x-cloak class="rounded-xl bg-slate-50 p-4">
                        <p class="mb-3 text-xs leading-6 text-slate-600">مبالغ ورودی به <strong>{{ $priceInputCurrency }}، پیش از مالیات</strong> هستند. @if (\App\Models\AppSetting::currency() === 'IRR') هر ۱۰ ریال برابر ۱ تومان است. @endif صفر، قیمت رایگان محسوب می‌شود.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label for="custom-running" class="mb-2 block text-xs font-bold">روشن · {{ $priceInputCurrency }} / ماه معادل</label><input id="custom-running" type="number" name="custom_monthly_price" min="0" max="1000000000000" :required="mode === 'custom'" :disabled="mode !== 'custom'" value="{{ old('custom_monthly_price', $vm->custom_monthly_price) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" dir="ltr"></div>
                            <div><label for="custom-stopped" class="mb-2 block text-xs font-bold">خاموش · {{ $priceInputCurrency }} / ماه معادل</label><input id="custom-stopped" type="number" name="custom_stopped_monthly_price" min="0" max="1000000000000" :required="mode === 'custom'" :disabled="mode !== 'custom'" value="{{ old('custom_stopped_monthly_price', $vm->custom_stopped_monthly_price) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" dir="ltr"></div>
                        </div>
                    </div>
                    <label for="pricing-reason" class="block text-xs font-bold text-slate-600">دلیل تغییر قیمت</label>
                    <input id="pricing-reason" name="reason" required minlength="3" maxlength="1000" value="{{ old('kind') === 'pricing' ? old('reason') : '' }}" placeholder="مثلاً: توافق قیمت اختصاصی با مشتری" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <button :disabled="busy" class="rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" x-text="busy ? 'در حال آماده‌سازی…' : 'مرور قیمت جدید'">مرور قیمت جدید</button>
                </fieldset>
            </form>
            @else
                <p class="mt-5 text-sm text-slate-500">تغییر قیمت به دسترسی مدیریت قیمت‌گذاری نیاز دارد.</p>
            @endadminAbility
        </div>
    </div>
</section>
