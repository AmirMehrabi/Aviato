@extends('customer.layout')

@section('title', 'ساخت VPS جدید')
@section('header_title', 'ساخت VPS جدید')
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
            canCreate: @js($canCreateVps),
            walletBalance: @js($wallet->balance),
            walletBalanceLabel: @js($wallets->format($wallet->balance)),
            minimumBalanceLabel: @js($wallets->format($minimumCreateBalance)),
            walletUrl: @js(route('customer.wallet.show', [], false)),
            osFamilies: @js($osFamilies),
            bundles: @js($bundles->map(fn ($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'cpu_cores' => $bundle->cpu_cores,
                'ram_gb' => $bundle->ram_gb,
                'disk_gb' => $bundle->disk_gb,
                'price' => $wallets->format($bundle->monthly_price),
                'monthly_price' => $bundle->monthly_price,
                'description' => $bundle->description ?: 'منابع پایدار برای VPS ابری',
            ])->values()),
            images: @js($cloudImages->map(fn ($image) => [
                'id' => $image->id,
                'name' => $image->name,
                'description' => $image->description,
                'os_family' => $image->os_family,
                'os_version' => $image->os_version,
                'logo_key' => $image->logo_key ?: $image->os_family,
                'server' => $image->proxmoxServer?->datacenter ?: $image->proxmoxServer?->name,
                'default_username' => $image->default_username,
                'cloud_init_enabled' => $image->cloud_init_enabled,
                'allowed_bundle_ids' => $image->allowedBundles->pluck('id')->values()->all(),
            ])->values()),
        })"
        class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]"
    >
        <form x-ref="createForm" method="POST" action="{{ route('customer.servers.store') }}" class="space-y-5">
            @csrf
            @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-black uppercase text-[#0069FF]">Step 1</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">سیستم عامل را انتخاب کنید</h2>
                </div>
                <div class="p-5">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <template x-for="family in osFamilies" :key="family.key">
                            <button
                                type="button"
                                @click="selectOs(family.key)"
                                class="group flex min-h-28 items-center gap-2 rounded-lg border p-2 text-right transition"
                                :class="form.os_family === family.key ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:border-[#B8D6FF] hover:bg-[#F8FBFF]'"
                            >
                                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-200" :class="logoFrameClasses(family.logo_key)">
                                    <img x-show="logoAsset(family.logo_key)" :src="logoAsset(family.logo_key)" :alt="family.label + ' logo'" class="size-full object-contain p-1.5">
                                    <span x-show="!logoAsset(family.logo_key)" class="text-base font-black" :class="logoClasses(family.logo_key)" x-text="logoText(family.logo_key)"></span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-black text-slate-950" x-text="family.label"></span>
                                    <span class="mt-1 block text-xs font-bold text-slate-500" x-text="`${family.count} نسخه آماده`"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="!osFamilies.length" class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm font-bold text-slate-500">فعلا هیچ Cloud Image فعالی منتشر نشده است.</p>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-black uppercase text-[#0069FF]">Step 2</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">نسخه را انتخاب کنید</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    <template x-for="image in filteredImages" :key="image.id">
                        <label class="flex cursor-pointer items-center gap-4 px-5 py-4 transition hover:bg-slate-50" :class="String(form.cloud_image_id) === String(image.id) ? 'bg-[#F2F8FF]' : 'bg-white'">
                            <input type="radio" name="cloud_image_id" :value="image.id" x-model="form.cloud_image_id" @change="applyImage()" class="size-4 border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                            <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-200" :class="logoFrameClasses(image.logo_key)">
                                <img x-show="logoAsset(image.logo_key)" :src="logoAsset(image.logo_key)" :alt="image.name + ' logo'" class="size-full object-contain p-1">
                                <span x-show="!logoAsset(image.logo_key)" class="text-sm font-black" :class="logoClasses(image.logo_key)" x-text="logoText(image.logo_key)"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-black text-slate-950" x-text="image.os_version || image.name"></span>
                                <span class="mt-1 block text-xs font-bold text-slate-500" x-text="image.description || image.server || (image.cloud_init_enabled ? 'CloudInit ready template' : 'Template بدون CloudInit')"></span>
                            </span>
                            <span x-show="!image.cloud_init_enabled" class="hidden rounded-md bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200 md:inline">No CloudInit</span>
                            <span class="hidden rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500 md:inline" x-text="image.server || 'Auto'"></span>
                        </label>
                    </template>
                    <p x-show="form.os_family && !filteredImages.length" class="px-5 py-6 text-center text-sm font-bold text-slate-500">برای این سیستم عامل نسخه‌ای منتشر نشده است.</p>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-black uppercase text-[#0069FF]">Step 3</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">پلن VPS را انتخاب کنید</h2>
                </div>
                    <div class="grid gap-4 p-5 lg:grid-cols-3">
                    <template x-for="(bundle, index) in visibleBundles" :key="bundle.id">
                        <label
                            class="relative cursor-pointer rounded-xl border p-4 text-right transition"
                            :class="planClasses(bundle, index)"
                        >
                            <input type="radio" name="vm_bundle_id" :value="bundle.id" x-model="form.vm_bundle_id" @change="applyBundle()" class="sr-only">
                            <span x-show="index === 1" class="absolute left-4 top-4 rounded-md bg-[#0069FF] px-2.5 py-1 text-[11px] font-black text-white">پیشنهادی</span>
                            <span x-show="index === bundles.length - 1 && bundles.length > 2" class="absolute left-4 top-4 rounded-md bg-slate-950 px-2.5 py-1 text-[11px] font-black text-white">پرقدرت</span>
                            <span class="block text-lg font-black text-slate-950" x-text="bundle.name"></span>
                            <span class="mt-2 block min-h-10 text-xs leading-6 text-slate-500" x-text="bundle.description"></span>
                            <span class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.cpu_cores"></b><br>CPU</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.ram_gb"></b><br>RAM</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.disk_gb"></b><br>Disk</span>
                            </span>
                            <span class="mt-5 block text-left text-xl font-black text-slate-950"><span x-text="bundle.price"></span> <small class="text-xs text-slate-500">/ ماه</small></span>
                        </label>
                    </template>
                </div>
                <div x-show="selectedImage && !visibleBundles.length" class="border-t border-slate-100 px-5 py-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-7 text-amber-900">
                        برای این نسخه فعلا هیچ پلنی تعریف نشده است. لطفا یک Cloud Image دیگر انتخاب کنید یا در بخش مدیریت برای این Image پلن تعریف کنید.
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-black uppercase text-[#0069FF]">Step 4</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">دسترسی اولیه</h2>
                </div>
                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">نام VPS</span>
                        <input name="name" x-model="form.name" @input="syncHostname()" dir="ltr" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#0069FF] focus:outline-none" placeholder="web-01">
                        @error('name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label x-show="cloudInitEnabled" class="block">
                        <span class="text-sm font-black text-slate-700">Hostname</span>
                        <input name="hostname" x-model="form.hostname" :disabled="!cloudInitEnabled" readonly dir="ltr" class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left font-bold text-slate-500 focus:outline-none">
                        <span class="mt-1 block text-xs text-slate-500">به صورت خودکار از نام VPS و سیستم عامل ساخته می‌شود.</span>
                    </label>
                    <label x-show="cloudInitEnabled" class="block">
                        <span class="text-sm font-black text-slate-700">Username</span>
                        <input name="login_username" x-model="form.login_username" :disabled="!cloudInitEnabled" dir="ltr" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#0069FF] focus:outline-none">
                        @error('login_username') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <div x-show="cloudInitEnabled">
                        <x-form.input name="login_password" type="password" label="Password" help="اختیاری؛ اگر SSH key خالی باشد password امن ساخته می‌شود." x-bind:disabled="!cloudInitEnabled" />
                    </div>
                    <label x-show="cloudInitEnabled" class="md:col-span-2">
                        <span class="text-sm font-black text-slate-700">SSH Public Key</span>
                        <textarea name="ssh_public_key" rows="4" dir="ltr" :disabled="!cloudInitEnabled" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#0069FF] focus:outline-none">{{ old('ssh_public_key') }}</textarea>
                        @error('ssh_public_key') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <div x-show="!cloudInitEnabled" class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-7 text-amber-900">
                        این template با CloudInit ساخته نشده است؛ username، hostname، password و SSH key هنگام ساخت تنظیم نمی‌شوند.
                    </div>
                </div>
            </section>

            <input type="hidden" name="cpu_cores" :value="form.cpu_cores">
            <input type="hidden" name="ram_gb" :value="form.ram_gb">
            <input type="hidden" name="disk_gb" :value="form.disk_gb">
        </form>

        <aside class="space-y-5">
            <div class="sticky top-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">خلاصه ساخت</h2>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">سیستم عامل</span><span class="font-black text-slate-950" x-text="selectedOsLabel || '—'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">نسخه</span><span class="font-black text-slate-950" x-text="selectedImage?.os_version || '—'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">CloudInit</span><span class="font-black text-slate-950" x-text="cloudInitEnabled ? 'فعال' : 'غیرفعال'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">پلن</span><span class="font-black text-slate-950" x-text="selectedBundle?.name || '—'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">منابع</span><span class="font-black text-slate-950" dir="ltr" x-text="`${form.cpu_cores} CPU / ${form.ram_gb}GB / ${form.disk_gb}GB`"></span></div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">کیف پول</p>
                        <p class="mt-2 text-xl font-black" :class="canCreate ? 'text-emerald-700' : 'text-red-700'" x-text="walletBalanceLabel"></p>
                        <p class="mt-1 text-xs leading-6 text-slate-500">حداقل موجودی برای ساخت VPS: <span x-text="minimumBalanceLabel"></span></p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">هزینه ماهانه تقریبی</p>
                        <p class="mt-2 text-2xl font-black text-slate-950" x-text="selectedBundle?.price || '—'"></p>
                    </div>
                    <div class="rounded-lg border border-dashed border-slate-300 p-4 text-xs leading-6 text-slate-500">اگر IP آزاد در Pool وجود داشته باشد، به صورت خودکار رزرو می‌شود.</div>
                </div>
                <button type="button" @click="submit()" :disabled="!canSubmit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-black transition" :class="canSubmit ? 'bg-[#0069FF] text-white hover:bg-[#0050D0]' : 'cursor-not-allowed bg-slate-200 text-slate-500'">
                    <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    <span x-text="submitting ? 'در حال ثبت درخواست...' : 'ساخت VPS'"></span>
                </button>
                <a x-show="!canCreate" :href="walletUrl" class="mt-3 inline-flex w-full justify-center rounded-lg border border-[#B8D6FF] bg-[#F2F8FF] px-4 py-3 text-sm font-black text-[#0069FF]">افزایش موجودی کیف پول</a>
                <p x-show="!canCreate" class="mt-3 text-xs leading-6 text-red-600">برای ساخت VPS موجودی کیف پول باید حداقل <span x-text="minimumBalanceLabel"></span> باشد.</p>
            </div>
        </aside>
    </section>

    <script>
    function customerVmCreate(config) {
        return {
            canCreate: config.canCreate,
            submitting: false,
            walletBalanceLabel: config.walletBalanceLabel,
            minimumBalanceLabel: config.minimumBalanceLabel,
            walletUrl: config.walletUrl,
            osFamilies: config.osFamilies,
            bundles: config.bundles,
            images: config.images,
            form: {
                os_family: '',
                cloud_image_id: @js((string) old('cloud_image_id', '')),
                vm_bundle_id: @js((string) old('vm_bundle_id', '')),
                cpu_cores: @js((int) old('cpu_cores', 2)),
                ram_gb: @js((int) old('ram_gb', 4)),
                disk_gb: @js((int) old('disk_gb', 50)),
                name: @js(old('name', '')),
                hostname: @js(old('hostname', '')),
                login_username: @js(old('login_username', 'ubuntu')),
            },
            init() {
                if (this.form.cloud_image_id && this.selectedImage) {
                    this.form.os_family = this.selectedImage.os_family;
                } else if (this.osFamilies.length) {
                    this.selectOs(this.osFamilies[0].key);
                }
                if (!this.form.vm_bundle_id && this.bundles.length) {
                    this.form.vm_bundle_id = String(this.bundles[0].id);
                    this.applyBundle();
                }
                this.syncHostname();
            },
            get filteredImages() { return this.images.filter((image) => image.os_family === this.form.os_family); },
            get selectedImage() { return this.images.find((image) => String(image.id) === String(this.form.cloud_image_id)); },
            get visibleBundles() {
                if (!this.selectedImage) return [];
                const allowed = new Set((this.selectedImage.allowed_bundle_ids || []).map((id) => String(id)));
                return this.bundles.filter((bundle) => allowed.has(String(bundle.id)));
            },
            get selectedBundle() { return this.visibleBundles.find((bundle) => String(bundle.id) === String(this.form.vm_bundle_id)); },
            get cloudInitEnabled() { return this.selectedImage ? Boolean(this.selectedImage.cloud_init_enabled) : true; },
            get selectedOsLabel() {
                return this.osFamilies.find((family) => family.key === this.form.os_family)?.label || '';
            },
            get canSubmit() {
                return !this.submitting && this.canCreate && this.form.cloud_image_id && this.form.vm_bundle_id && this.selectedBundle && this.form.name.trim().length > 0;
            },
            selectOs(family) {
                this.form.os_family = family;
                const firstImage = this.filteredImages[0];
                this.form.cloud_image_id = firstImage ? String(firstImage.id) : '';
                this.applyImage();
            },
            applyImage() {
                if (!this.selectedImage) return;
                this.form.login_username = this.cloudInitEnabled ? (this.selectedImage.default_username || 'ubuntu') : '';
                this.syncHostname();
                this.syncBundleSelection();
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
            syncHostname() {
                if (!this.cloudInitEnabled) {
                    this.form.hostname = '';
                    return;
                }
                const name = this.slug(this.form.name || 'vps');
                const os = this.slug(this.selectedOsLabel || this.form.os_family || 'cloud');
                this.form.hostname = `${os}-${name}`.replace(/^-+|-+$/g, '');
            },
            submit() {
                if (!this.canSubmit) return;
                this.submitting = true;
                this.$refs.createForm.submit();
            },
            slug(value) {
                return String(value || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 48);
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
