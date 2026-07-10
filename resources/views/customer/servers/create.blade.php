@extends('customer.layout')

@section('title', 'ساخت ماشین مجازی جدید')
@section('header_title', 'ساخت ماشین مجازی جدید')
@section('header_subtitle', 'سیستم عامل، نسخه، پلن و دسترسی اولیه را انتخاب کنید')

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
    <section
        x-data="customerVmCreate({
            walletBalance: @js($wallet->balance),
            walletBalanceLabel: @js($wallets->format($wallet->balance)),
            walletUrl: @js(route('customer.wallet.show', [], false)),
            profileUrl: @js(route('customer.profile.show', [], false)),
            quota: @js($quota),
            namePeriod: @js(now()->format('ym')),
            taxEnabled: @js($taxEnabled),
            taxRatePercentage: @js($taxRatePercentage),
            currency: @js(\App\Models\AppSetting::currency()),
            osFamilies: @js($osFamilies),
            locations: @js($locations->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'provider' => $location->provider,
                'region' => $location->region,
                'remote_name' => $location->remote_name,
                'hetzner_account_id' => $location->hetzner_account_id,
                'proxmox_server_id' => $location->proxmox_server_id,
                'bundle_ids' => $location->bundleMappings->pluck('vm_bundle_id')->map(fn ($id) => (int) $id)->values()->all(),
            ])->values()),
            locationMappings: @js($locationMappings->map(fn ($mapping) => [
                'location_id' => $mapping->infrastructure_location_id,
                'bundle_id' => $mapping->vm_bundle_id,
                'hetzner_server_type' => $mapping->hetznerServerType?->name,
                'monthly_price_usd' => $mapping->monthly_price_usd,
                'monthly_price_irr' => $mapping->monthly_price_irr,
                'usd_to_irr_rate' => $mapping->usd_to_irr_rate,
            ])->values()),
            bundles: @js($bundles->map(fn ($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'cpu_cores' => $bundle->cpu_cores,
                'ram_gb' => $bundle->ram_gb,
                'disk_gb' => $bundle->disk_gb,
                'price' => $wallets->format($bundle->monthly_price),
                'monthly_price' => $bundle->monthly_price,
                'minimum_create_balance' => max((int) ceil($bundle->monthly_price / 2), \App\Models\AppSetting::vmCreationChargeAmount((int) $bundle->monthly_price)),
                'minimum_create_balance_label' => $wallets->format(max((int) ceil($bundle->monthly_price / 2), \App\Models\AppSetting::vmCreationChargeAmount((int) $bundle->monthly_price))),
                'description' => $bundle->description ?: '',
            ])->values()),
            images: @js($cloudImages->map(fn ($image) => [
                'id' => $image->id,
                'name' => $image->name,
                'description' => $image->description,
                'os_family' => $image->os_family,
                'os_version' => $image->os_version,
                'logo_key' => $image->logo_key ?: $image->os_family,
                'provider' => $image->provider ?: 'proxmox',
                'infrastructure_location_id' => $image->infrastructure_location_id,
                'hetzner_account_id' => data_get($image->provider_metadata, 'hetzner_account_id'),
                'server' => $image->proxmoxServer?->datacenter ?: $image->proxmoxServer?->name,
                'default_username' => $image->default_username,
                'cloud_init_enabled' => $image->cloud_init_enabled,
                'allowed_bundle_ids' => $image->allowedBundles->pluck('id')->values()->all(),
                'available_ip_count' => (int) ($ipAvailability[$image->id] ?? 0),
                'has_available_ip' => (int) ($ipAvailability[$image->id] ?? 0) > 0,
            ])->values()),
        })"
        class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]"
    >
        <form x-ref="createForm" method="POST" action="{{ route('customer.servers.store') }}" class="space-y-5">
            @csrf
            @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold leading-6 text-red-800 shadow-sm">{{ $errors->first() }}</div>@endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">۱</span>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-widest text-[#0069FF]">Step 1</p>
                            <h2 class="mt-0.5 text-xl font-black text-slate-950">موقعیت سرور را انتخاب کنید</h2>
                        </div>
                    </div>
                    <p class="mt-3 pr-12 text-xs font-bold text-slate-500">نزدیک‌ترین موقعیت به کاربران یا سرویس خود را انتخاب کنید.</p>
                </div>
                <div class="p-5 sm:p-6">
                    <input type="hidden" name="infrastructure_location_id" :value="form.infrastructure_location_id">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <template x-for="location in locations" :key="location.id">
                            <button
                                type="button"
                                @click="selectLocation(location.id)"
                                class="group relative min-h-24 rounded-xl border p-4 text-right transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                :class="String(form.infrastructure_location_id) === String(location.id) ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:border-[#B8D6FF] hover:bg-[#F8FBFF]'"
                            >
                                <span class="block text-lg font-black text-slate-950" x-text="location.name"></span>
                                <span class="mt-2 block text-xs font-bold text-slate-500" x-text="location.region || location.remote_name || 'Location'"></span>
                                <span x-show="String(form.infrastructure_location_id) === String(location.id)" class="absolute left-3 top-3 grid size-5 place-items-center rounded-full bg-[#0069FF] text-[10px] font-black text-white">✓</span>
                            </button>
                        </template>
                    </div>
                    <p x-show="!locations.length" class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm font-bold text-slate-500">فعلا هیچ موقعیت فعالی برای ساخت ماشین مجازی تعریف نشده است.</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">۲</span>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-widest text-[#0069FF]">Step 2</p>
                            <h2 class="mt-0.5 text-xl font-black text-slate-950">سیستم عامل و نسخه را انتخاب کنید</h2>
                        </div>
                    </div>
                    <p class="mt-3 pr-12 text-xs font-bold text-slate-500">یک خانواده را انتخاب کنید، سپس نسخه موردنظر را از فهرست بازشونده مشخص کنید.</p>
                </div>
                <div class="p-5 sm:p-6">
                    <input type="hidden" name="cloud_image_id" :value="form.cloud_image_id">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <template x-for="family in osFamilies" :key="family.key">
                            <div
                                @click="selectOs(family.key)"
                                class="group min-h-32 cursor-pointer rounded-xl border p-3 text-right transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                :class="form.os_family === family.key ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:border-[#B8D6FF] hover:bg-[#F8FBFF]'"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-200" :class="logoFrameClasses(family.logo_key)">
                                        <img x-show="logoAsset(family.logo_key)" :src="logoAsset(family.logo_key)" :alt="family.label + ' logo'" class="size-full object-contain p-1.5">
                                        <span x-show="!logoAsset(family.logo_key)" class="text-base font-black" :class="logoClasses(family.logo_key)" x-text="logoText(family.logo_key)"></span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-black text-slate-950" x-text="family.label"></span>
                                        <span class="mt-1 block text-xs font-bold text-slate-500" x-text="selectedImageForFamily(family.key)?.os_version || selectedImageForFamily(family.key)?.name || `${family.count} نسخه آماده`"></span>
                                    </span>
                                </div>
                                <label x-show="form.os_family === family.key" x-cloak class="mt-4 block cursor-default" @click.stop>
                                    <select
                                        x-model="form.cloud_image_id"
                                        @change="applyImage()"
                                        class="w-full rounded-lg border border-[#B8D6FF] bg-white px-3 py-2.5 text-sm font-black text-slate-800 shadow-sm focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10"
                                    >
                                        <template x-for="image in imagesForFamily(family.key)" :key="image.id">
                                            <option :value="String(image.id)" x-text="image.os_version || image.name"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>
                        </template>
                    </div>
                    <p x-show="!osFamilies.length" class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm font-bold text-slate-500">فعلا هیچ Cloud Image فعالی منتشر نشده است.</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">۳</span>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-widest text-[#0069FF]">Step 3</p>
                            <h2 class="mt-0.5 text-xl font-black text-slate-950">پلن ماشین مجازی را انتخاب کنید</h2>
                        </div>
                    </div>
                    <p class="mt-3 pr-12 text-xs font-bold text-slate-500">منابع موردنیاز ماشین و هزینه ماهانه را با هم مقایسه کنید.</p>
                </div>
                <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-3">
                    <template x-for="(bundle, index) in visibleBundles" :key="bundle.id">
                        <label
                            class="relative flex min-h-64 cursor-pointer flex-col rounded-xl border p-4 text-right transition duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                            :class="planClasses(bundle, index)"
                        >
                            <input type="radio" name="vm_bundle_id" :value="bundle.id" x-model="form.vm_bundle_id" @change="applyBundle()" class="sr-only">
                            <span x-show="index === 1" class="absolute left-4 top-4 rounded-md bg-[#0069FF] px-2.5 py-1 text-[11px] font-black text-white">پیشنهادی</span>
                            <span x-show="index === bundles.length - 1 && bundles.length > 2" class="absolute left-4 top-4 rounded-md bg-slate-950 px-2.5 py-1 text-[11px] font-black text-white">پرقدرت</span>
                            <span class="block text-lg font-black text-slate-950" x-text="bundle.name"></span>
                            <span class="mt-2 block min-h-10 text-xs leading-6 text-slate-500" x-text="bundle.description"></span>
                            <span class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                                <span class="rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200"><b class="block text-base text-slate-950" x-text="bundle.cpu_cores"></b><span class="text-slate-500">CPU</span></span>
                                <span class="rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200"><b class="block text-base text-slate-950" x-text="bundle.ram_gb"></b><span class="text-slate-500">RAM</span></span>
                                <span class="rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200"><b class="block text-base text-slate-950" x-text="bundle.disk_gb"></b><span class="text-slate-500">Disk</span></span>
                            </span>
                            <span class="mt-auto border-t border-slate-100 pt-4 text-left text-xl font-black text-slate-950"><span x-text="bundle.price"></span> <small class="text-xs font-bold text-slate-500">/ ماه</small></span>
                        </label>
                    </template>
                </div>
                <div x-show="selectedImage && !visibleBundles.length" class="border-t border-slate-100 px-5 py-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-7 text-amber-900">
                        برای این نسخه فعلا هیچ پلنی تعریف نشده است. لطفا یک Cloud Image دیگر انتخاب کنید یا در بخش مدیریت برای این Image پلن تعریف کنید.
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">۴</span>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-widest text-[#0069FF]">Step 4</p>
                            <h2 class="mt-0.5 text-xl font-black text-slate-950">دسترسی اولیه</h2>
                        </div>
                    </div>
                    <p class="mt-3 pr-12 text-xs font-bold text-slate-500">نام و اطلاعات ورود اولیه ماشین را در صورت نیاز تنظیم کنید.</p>
                </div>
                <div class="grid gap-5 p-5 sm:p-6">
                    <div class="rounded-xl border border-[#D7E8FF] bg-[#F8FBFF] p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-black text-slate-700">شناسه ماشین مجازی</span>
                            <span class="break-all text-left text-lg font-black text-slate-950" dir="ltr" x-text="generatedNamePreview"></span>
                        </div>
                    </div>

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">نام نمایشی (اختیاری)</span>
                        <input name="display_name" x-model="form.display_name" maxlength="128" dir="ltr" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition placeholder:text-slate-400 focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10" placeholder="My Production Server">
                        <span class="mt-1 block text-xs font-bold text-slate-400">نامی که در پنل نمایش داده می‌شود. در صورت خالی بودن، شناسه خودکار نمایش داده خواهد شد.</span>
                        @error('display_name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <div x-show="cloudInitEnabled" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-black text-slate-700">Username</span>
                                <input name="login_username" x-model="form.login_username" :disabled="!cloudInitEnabled" dir="ltr" autocomplete="username" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10 disabled:cursor-not-allowed disabled:bg-slate-100">
                                @error('login_username') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-black text-slate-700">SSH Public Key</span>
                                    <span x-show="sshKeyAdded" class="text-xs font-black text-emerald-700">Key added</span>
                                </span>
                                <textarea name="ssh_public_key" x-model="form.ssh_public_key" rows="3" dir="ltr" :disabled="!cloudInitEnabled" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-sm shadow-sm transition focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10 disabled:cursor-not-allowed disabled:bg-slate-100" placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA..."></textarea>
                                <span x-show="sshKeyInvalid" x-cloak class="mt-1 block text-xs font-bold text-red-600">فرمت کلید SSH معتبر نیست.</span>
                                @error('ssh_public_key') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-black text-slate-700">Password</span>
                                <input name="login_password" x-model="form.login_password" type="password" :disabled="!cloudInitEnabled" dir="ltr" autocomplete="new-password" placeholder="Auto generate" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition placeholder:text-slate-400 focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10 disabled:cursor-not-allowed disabled:bg-slate-100">
                                @error('login_password') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="text-sm font-black text-slate-700">Confirm Password</span>
                                <input name="login_password_confirmation" x-model="form.login_password_confirmation" type="password" :disabled="!cloudInitEnabled" dir="ltr" autocomplete="new-password" placeholder="Auto generate" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition placeholder:text-slate-400 focus:border-[#0069FF] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/10 disabled:cursor-not-allowed disabled:bg-slate-100">
                            </label>
                        </div>
                    </div>

                </div>
            </section>

            <input type="hidden" name="cpu_cores" :value="form.cpu_cores">
            <input type="hidden" name="ram_gb" :value="form.ram_gb">
            <input type="hidden" name="disk_gb" :value="form.disk_gb">
            <input type="hidden" name="requires_invoice" :value="form.requires_invoice ? '1' : '0'">
        </form>

        <aside class="space-y-5">
            <div class="sticky top-24 overflow-hidden rounded-2xl border border-[#B8D6FF] bg-[#F4F8FF] shadow-xl shadow-[#0069FF]/10 ring-1 ring-white">
                <div class="border-b border-[#D7E8FF] bg-white px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase text-[#0069FF]">Summary</p>
                            <h2 class="mt-1 text-lg font-black text-slate-950">حساب و کتاب</h2>
                        </div>
                        <span class="rounded-full bg-[#EBF3FF] px-3 py-1 text-[11px] font-black text-[#0069FF]">آماده بررسی</span>
                    </div>
                </div>

                <div class="p-5">
                    <div class="space-y-3 rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-[#D7E8FF]">
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">موقعیت</span><span class="text-left font-black text-slate-950" x-text="selectedLocation?.name || '—'"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">سیستم عامل</span><span class="text-left font-black text-slate-950" x-text="selectedOsLabel || '—'"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">نسخه</span><span class="text-left font-black text-slate-950" x-text="selectedImage?.os_version || '—'"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">CloudInit</span><span class="text-left font-black text-slate-950" x-text="cloudInitEnabled ? 'فعال' : 'غیرفعال'"></span></div>
                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3"><span class="font-bold text-slate-500">پلن</span><span class="text-left font-black text-slate-950" x-text="selectedBundle?.name || '—'"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">منابع</span><span class="text-left font-black text-slate-950" dir="ltr" x-text="`${form.cpu_cores} CPU / ${form.ram_gb}GB / ${form.disk_gb}GB`"></span></div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-[#D7E8FF] bg-white p-5 shadow-sm shadow-[#0069FF]/10">
                        <p class="text-xs font-black text-[#0069FF]">هزینه ماهانه تقریبی</p>
                        <p class="mt-2 text-3xl font-black leading-tight tracking-tight text-slate-950" x-text="displayMonthlyPrice"></p>
                        <p x-show="showsTax" x-cloak class="mt-1 text-xs font-bold text-slate-500" x-text="`شامل مالیات ${taxRatePercentage}%`"></p>
                        <p class="mt-2 text-xs font-bold leading-6 text-slate-500">پس از ساخت، مصرف PAYG از کیف پول پروژه محاسبه می شود.</p>
                    </div>

                    <div x-show="taxEnabled" class="mt-4">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#B8D6FF] bg-white p-4 transition hover:border-[#0069FF] hover:shadow-sm">
                            <input type="checkbox" x-model="form.requires_invoice" class="size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                            <span class="text-xs font-bold leading-6 text-slate-600">نیاز به صورتحساب رسمی (شامل مالیات)</span>
                        </label>
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div x-show="walletNeedsTopUp" x-cloak class="rounded-xl border border-red-100 bg-red-50 p-4">
                            <p class="text-xs font-black text-red-700">کیف پول کافی نیست</p>
                            <p class="mt-2 text-xs leading-6 text-red-600">برای ساخت این ماشین مجازی موجودی کیف پول باید حداقل <span x-text="minimumBalanceLabel"></span> باشد.</p>
                        </div>
                        <div x-show="selectedImage && !selectedImage.has_available_ip" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-black text-amber-800">ظرفیت IP محدود است</p>
                            <p class="mt-2 text-xs leading-6 text-amber-800">در حال حاضر IP آزاد برای این نسخه وجود ندارد. تا آزاد شدن یا اضافه شدن IP جدید، امکان ساخت ماشین مجازی وجود ندارد.</p>
                        </div>
                        @if (! $quota['can_create'])
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-xs font-black text-amber-800">امکان ساخت ماشین مجازی جدید وجود ندارد</p>
                                <p class="mt-2 text-xs leading-6 text-amber-800">
                                    @if (! $quota['verified'])
                                        برای ساخت ماشین مجازی بیشتر، کد ملی‌تان را در پروفایل تایید کنید.
                                    @else
                                        در حال حاضر ظرفیت ساخت ماشین مجازی برای این حساب محدود است و امکان ساخت ماشین جدید وجود ندارد.
                                    @endif
                                </p>
                            </div>
                        @endif

                    </div>
                </div>

                <div class="border-t border-[#D7E8FF] bg-white/80 p-5">
                    <button type="button" @click="submit()" :disabled="!canSubmit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-black transition" :class="canSubmit ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/25 hover:bg-[#0050D0]' : 'cursor-not-allowed bg-slate-200 text-slate-500 shadow-none'">
                        <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        <span x-text="submitting ? 'در حال ثبت درخواست...' : 'ساخت ماشین مجازی'"></span>
                    </button>
                    <a x-show="walletNeedsTopUp" x-cloak :href="walletUrl" class="mt-3 inline-flex w-full justify-center rounded-xl border border-[#B8D6FF] bg-[#F2F8FF] px-4 py-3 text-sm font-black text-[#0069FF]">افزایش موجودی کیف پول</a>
                    @if (! $quota['can_create'] && ! $quota['verified'])
                        <a href="{{ route('customer.profile.show', [], false) }}" class="mt-3 inline-flex w-full justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">تایید کد ملی در پروفایل</a>
                    @endif
                </div>
            </div>
        </aside>
    </section>

    <script>
    function customerVmCreate(config) {
        return {
            submitting: false,
            walletBalance: Number(config.walletBalance || 0),
            walletBalanceLabel: config.walletBalanceLabel,
            walletUrl: config.walletUrl,
            profileUrl: config.profileUrl,
            quota: config.quota,
            taxEnabled: config.taxEnabled || false,
            taxRatePercentage: Number(config.taxRatePercentage || 0),
            currency: config.currency || 'IRR',
            osFamilies: config.osFamilies,
            locations: config.locations || [],
            locationMappings: config.locationMappings || [],
            bundles: config.bundles,
            images: config.images,
            namePeriod: config.namePeriod || '',
            form: {
                infrastructure_location_id: @js((string) old('infrastructure_location_id', '')),
                os_family: '',
                cloud_image_id: @js((string) old('cloud_image_id', '')),
                vm_bundle_id: @js((string) old('vm_bundle_id', '')),
                display_name: @js(old('display_name', '')),
                cpu_cores: @js((int) old('cpu_cores', 2)),
                ram_gb: @js((int) old('ram_gb', 4)),
                disk_gb: @js((int) old('disk_gb', 50)),
                login_username: @js(old('login_username', 'ubuntu')),
                login_password: '',
                login_password_confirmation: '',
                ssh_public_key: @js(old('ssh_public_key', '')),
                requires_invoice: false,
            },
            init() {
                if (!this.form.infrastructure_location_id && this.locations.length) {
                    this.form.infrastructure_location_id = String(this.locations[0].id);
                }
                if (this.form.cloud_image_id && this.selectedImage) {
                    this.form.os_family = this.selectedImage.os_family;
                    this.applyImage();
                } else if (this.osFamilies.length) {
                    this.selectOs(this.osFamilies[0].key);
                }
                if (!this.form.vm_bundle_id && this.bundles.length) {
                    this.form.vm_bundle_id = String(this.bundles[0].id);
                    this.applyBundle();
                }
            },
            get selectedLocation() { return this.locations.find((location) => String(location.id) === String(this.form.infrastructure_location_id)); },
            get selectedImage() { return this.images.find((image) => String(image.id) === String(this.form.cloud_image_id)); },
            get availableImages() {
                if (!this.selectedLocation) return [];
                return this.images.filter((image) => {
                    if (this.selectedLocation.provider === 'proxmox') {
                        return image.provider === 'proxmox' && String(image.infrastructure_location_id) === String(this.selectedLocation.id);
                    }

                    return image.provider === 'hetzner' && String(image.hetzner_account_id) === String(this.selectedLocation.hetzner_account_id);
                });
            },
            get visibleBundles() {
                if (!this.selectedImage || !this.selectedLocation) return [];
                if (this.selectedLocation.provider === 'proxmox') {
                    const allowed = new Set((this.selectedImage.allowed_bundle_ids || []).map((id) => String(id)));
                    return this.bundles.filter((bundle) => allowed.has(String(bundle.id)));
                }

                const allowed = new Set((this.selectedLocation.bundle_ids || []).map((id) => String(id)));
                return this.bundles
                    .filter((bundle) => allowed.has(String(bundle.id)))
                    .map((bundle) => this.bundleWithLocationPrice(bundle));
            },
            get selectedBundle() { return this.visibleBundles.find((bundle) => String(bundle.id) === String(this.form.vm_bundle_id)); },
            get cloudInitEnabled() { return this.selectedImage ? Boolean(this.selectedImage.cloud_init_enabled) : true; },
            get selectedOsLabel() {
                return this.osFamilies.find((family) => family.key === this.form.os_family)?.label || '';
            },
            get minimumBalanceLabel() {
                return this.selectedBundle?.minimum_create_balance_label || '—';
            },
            get walletCanCreate() {
                return this.selectedBundle && this.walletBalance >= Number(this.selectedBundle.minimum_create_balance || 0);
            },
            get walletNeedsTopUp() {
                return Boolean(this.selectedBundle) && !this.walletCanCreate;
            },
            get canCreate() {
                return this.walletCanCreate && Boolean(this.quota.can_create) && Boolean(this.selectedImage?.has_available_ip);
            },
            get canSubmit() {
                return !this.submitting && this.canCreate && this.form.infrastructure_location_id && this.form.cloud_image_id && this.form.vm_bundle_id && this.selectedBundle && !this.sshKeyInvalid;
            },
            get showsTax() {
                return this.taxEnabled && this.form.requires_invoice && this.selectedBundle;
            },
            get monthlyBasePrice() {
                return this.selectedBundle?.monthly_price || 0;
            },
            get monthlyTaxAmount() {
                if (!this.showsTax) return 0;
                return Math.round(this.monthlyBasePrice * this.taxRatePercentage / 100);
            },
            get monthlyPriceWithTax() {
                return this.monthlyBasePrice + this.monthlyTaxAmount;
            },
            formatDisplayAmount(amount) {
                const displayAmount = this.currency === 'IRR' ? amount / 10 : amount;

                return new Intl.NumberFormat('fa-IR').format(displayAmount);
            },
            get displayMonthlyPrice() {
                if (!this.selectedBundle) return '—';
                if (!this.showsTax) return this.selectedBundle.price;
                return this.formatDisplayAmount(this.monthlyPriceWithTax) + ' تومان';
            },
            get generatedNamePreview() {
                return `${this.osPrefix}-${this.namePeriod || 'YYMM'}-${this.bundleSpecsToken()}-XXXXXX`;
            },
            get osPrefix() {
                const prefixes = { ubuntu: 'UBNT', debian: 'DBN', rocky: 'RKL', router_os: 'ROS', windows: 'WND' };
                return prefixes[this.form.os_family] || 'VM';
            },
            get sshKeyAdded() {
                return this.form.ssh_public_key.trim().length > 0;
            },
            get sshKeyInvalid() {
                return this.cloudInitEnabled && !this.validSshPublicKeyInput(this.form.ssh_public_key);
            },
            selectLocation(locationId) {
                this.form.infrastructure_location_id = String(locationId);
                const currentStillAvailable = this.selectedImage && this.availableImages.some((image) => String(image.id) === String(this.selectedImage.id));
                if (!currentStillAvailable) {
                    const firstImage = this.availableImages[0] || null;
                    this.form.os_family = firstImage?.os_family || '';
                    this.form.cloud_image_id = firstImage ? String(firstImage.id) : '';
                }
                this.applyImage();
            },
            imagesForFamily(family) {
                return this.availableImages.filter((image) => image.os_family === family);
            },
            selectedImageForFamily(family) {
                if (this.form.os_family === family && this.selectedImage) return this.selectedImage;
                return this.imagesForFamily(family)[0] || null;
            },
            selectOs(family) {
                this.form.os_family = family;
                const current = this.selectedImage && this.selectedImage.os_family === family ? this.selectedImage : null;
                const firstImage = current || this.imagesForFamily(family)[0];
                this.form.cloud_image_id = firstImage ? String(firstImage.id) : '';
                this.applyImage();
            },
            applyImage() {
                if (!this.selectedImage) return;
                this.form.login_username = this.cloudInitEnabled ? (this.selectedImage.default_username || 'ubuntu') : '';
                this.syncBundleSelection();
            },
            bundleWithLocationPrice(bundle) {
                if (!this.selectedLocation || this.selectedLocation.provider !== 'hetzner') return bundle;

                const mapping = this.locationMappings.find((item) => String(item.location_id) === String(this.selectedLocation.id) && String(item.bundle_id) === String(bundle.id));
                if (!mapping || !mapping.monthly_price_irr) return bundle;

                return {
                    ...bundle,
                    price: new Intl.NumberFormat('fa-IR').format(Number(mapping.monthly_price_irr)) + ' IRR',
                    monthly_price: Number(mapping.monthly_price_irr),
                    minimum_create_balance: Math.max(Math.ceil(Number(mapping.monthly_price_irr) / 2), Number(bundle.minimum_create_balance || 0)),
                    minimum_create_balance_label: new Intl.NumberFormat('fa-IR').format(Math.max(Math.ceil(Number(mapping.monthly_price_irr) / 2), Number(bundle.minimum_create_balance || 0))) + ' IRR',
                };
            },
            applyBundle() {
                if (!this.selectedBundle) return;
                this.form.cpu_cores = this.selectedBundle.cpu_cores;
                this.form.ram_gb = this.selectedBundle.ram_gb;
                this.form.disk_gb = this.selectedBundle.disk_gb;
            },
            syncBundleSelection() {
                if (!this.selectedImage) {
                    this.form.vm_bundle_id = '';
                    return;
                }

                const current = this.visibleBundles.find((bundle) => String(bundle.id) === String(this.form.vm_bundle_id));
                const nextBundle = current || this.visibleBundles[0];

                if (nextBundle) {
                    this.form.vm_bundle_id = String(nextBundle.id);
                    this.applyBundle();
                    return;
                }

                this.form.vm_bundle_id = '';
                this.applyMinimumResources();
            },
            applyMinimumResources() {
                if (!this.selectedImage) return;
                this.form.cpu_cores = Math.max(Number(this.form.cpu_cores || 0), Number(this.selectedImage.min_cpu_cores || 1));
                this.form.ram_gb = Math.max(Number(this.form.ram_gb || 0), Number(this.selectedImage.min_ram_gb || 1));
                this.form.disk_gb = Math.max(Number(this.form.disk_gb || 0), Number(this.selectedImage.min_disk_gb || 10));
            },
            submit() {
                if (!this.canSubmit) return;
                this.submitting = true;
                this.$refs.createForm.submit();
            },
            bundleSpecsToken() {
                const bundle = this.selectedBundle || {};
                const cpu = Number(bundle.cpu_cores || this.form.cpu_cores || 0);
                const ram = Number(bundle.ram_gb || this.form.ram_gb || 0);
                const disk = Number(bundle.disk_gb || this.form.disk_gb || 0);
                return `${cpu}C${ram}G${disk}G`;
            },
            validSshPublicKeyInput(value) {
                const lines = String(value || '').split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
                if (!lines.length) return true;

                return lines.every((line) => {
                    const parts = line.split(/\s+/);
                    const type = parts[0] || '';
                    const encoded = parts[1] || '';
                    if (!['ssh-ed25519', 'ssh-rsa', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521', 'sk-ssh-ed25519@openssh.com', 'sk-ecdsa-sha2-nistp256@openssh.com'].includes(type)) return false;
                    if (!/^[A-Za-z0-9+/]+={0,2}$/.test(encoded)) return false;

                    try {
                        const decoded = atob(encoded);
                        if (decoded.length < 8) return false;
                        const length = (decoded.charCodeAt(0) << 24) | (decoded.charCodeAt(1) << 16) | (decoded.charCodeAt(2) << 8) | decoded.charCodeAt(3);
                        if (length <= 0 || decoded.length < 4 + length) return false;
                        return decoded.slice(4, 4 + length) === type;
                    } catch (error) {
                        return false;
                    }
                });
            },
            logoText(key) {
                return { ubuntu: 'U', debian: 'D', rocky: 'R', windows: 'W' }[key] || 'OS';
            },
            logoAsset(key) {
                return {
                    ubuntu: @js(asset('assets/images/distro/ubuntu.png')),
                    debian: @js(asset('assets/images/distro/debian.png')),
                    router_os: @js(asset('assets/images/distro/router_os.png')),
                }[key] || '';
            },
            logoFrameClasses(key) {
                return {
                    ubuntu: 'bg-orange-50',
                    debian: 'bg-red-50',
                    router_os: 'bg-slate-50',
                    rocky: 'bg-emerald-50',
                    windows: 'bg-sky-50',
                }[key] || 'bg-slate-50';
            },
            logoClasses(key) {
                return {
                    ubuntu: 'bg-[#E95420] text-white',
                    debian: 'bg-[#A81D33] text-white',
                    router_os: 'bg-slate-900 text-white',
                    rocky: 'bg-[#10B981] text-white',
                    windows: 'bg-[#0078D4] text-white',
                }[key] || 'bg-slate-900 text-white';
            },
            planClasses(bundle, index) {
                const selected = String(this.form.vm_bundle_id) === String(bundle.id);
                if (selected) return 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10';
                if (index === this.visibleBundles.length - 1 && this.visibleBundles.length > 2) return 'border-slate-300 bg-slate-50 hover:border-slate-400';
                return 'border-slate-200 bg-white hover:border-[#B8D6FF] hover:bg-[#F8FBFF]';
            },
        };
    }
    </script>
@endsection
