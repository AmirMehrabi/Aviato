@extends('customer.layout')

@section('title', 'ساخت ماشین')
@section('header_title', 'ساخت ماشین')
@section('header_subtitle', 'انتخاب Image، منابع و دسترسی اولیه برای VPS جدید')

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
            bundles: @js($bundles->map(fn ($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'cpu_cores' => $bundle->cpu_cores,
                'ram_gb' => $bundle->ram_gb,
                'disk_gb' => $bundle->disk_gb,
                'price' => $wallets->format($bundle->monthly_price),
                'description' => $bundle->description ?: 'باندل آماده برای ساخت سریع VPS',
            ])->values()),
            images: @js($cloudImages->map(fn ($image) => [
                'id' => $image->id,
                'name' => $image->name,
                'description' => $image->description,
                'server' => $image->proxmoxServer?->datacenter ?: $image->proxmoxServer?->name,
                'default_username' => $image->default_username,
                'min_cpu_cores' => $image->min_cpu_cores,
                'min_ram_gb' => $image->min_ram_gb,
                'min_disk_gb' => $image->min_disk_gb,
            ])->values()),
        })"
        class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        <form x-ref="createForm" method="POST" action="{{ route('customer.servers.store') }}" class="space-y-5">
            @csrf
            @if (session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-sm font-black text-[#0069FF]">Step 1</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Cloud Image را انتخاب کنید</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="image in images" :key="image.id">
                        <label class="block cursor-pointer rounded-lg border p-4 transition" :class="String(form.cloud_image_id) === String(image.id) ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:bg-slate-50'">
                            <input type="radio" name="cloud_image_id" :value="image.id" x-model="form.cloud_image_id" @change="applyImage()" class="sr-only">
                            <span class="block font-black text-slate-950" x-text="image.name"></span>
                            <span class="mt-1 block text-xs font-bold text-slate-500" x-text="image.server"></span>
                            <span class="mt-3 block min-h-10 text-xs leading-6 text-slate-500" x-text="image.description || 'Cloud-init ready template'"></span>
                            <span class="mt-3 block text-xs font-black text-slate-700" x-text="`Min ${image.min_cpu_cores} CPU / ${image.min_ram_gb}GB / ${image.min_disk_gb}GB`"></span>
                        </label>
                    </template>
                </div>
                @error('cloud_image_id') <span class="mt-2 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-sm font-black text-[#0069FF]">Step 2</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">پلن VPS را انتخاب کنید</h2>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <template x-for="bundle in bundles" :key="bundle.id">
                        <label class="cursor-pointer rounded-xl border p-4 text-right transition" :class="String(form.vm_bundle_id) === String(bundle.id) ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white hover:bg-slate-50'">
                            <input type="radio" name="vm_bundle_id" :value="bundle.id" x-model="form.vm_bundle_id" @change="applyBundle()" class="sr-only">
                            <span class="block font-black text-slate-950" x-text="bundle.name"></span>
                            <span class="mt-2 block min-h-10 text-xs leading-6 text-slate-500" x-text="bundle.description"></span>
                            <span class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.cpu_cores"></b><br>CPU</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.ram_gb"></b><br>RAM</span>
                                <span class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><b x-text="bundle.disk_gb"></b><br>Disk</span>
                            </span>
                            <span class="mt-4 block text-left text-lg font-black text-slate-950"><span x-text="bundle.price"></span> <small class="text-xs text-slate-500">/ ماه</small></span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-sm font-black text-[#0069FF]">Step 3</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">نام و دسترسی</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-form.input name="name" label="نام VPS" value="" dir-ltr />
                    <x-form.input name="hostname" label="Hostname" value="" dir-ltr />
                    <x-form.input name="login_username" label="Username" value="ubuntu" dir-ltr x-model="form.login_username" />
                    <x-form.input name="login_password" type="password" label="Password" help="اختیاری؛ اگر SSH key خالی باشد password امن ساخته می‌شود." />
                    <label class="md:col-span-2">
                        <span class="text-sm font-black text-slate-700">SSH Public Key</span>
                        <textarea name="ssh_public_key" rows="4" dir="ltr" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#0069FF] focus:outline-none">{{ old('ssh_public_key') }}</textarea>
                        @error('ssh_public_key') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            <input type="hidden" name="cpu_cores" :value="form.cpu_cores">
            <input type="hidden" name="ram_gb" :value="form.ram_gb">
            <input type="hidden" name="disk_gb" :value="form.disk_gb">
        </form>

        <aside class="space-y-5">
            <div class="sticky top-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">خلاصه ساخت</h2>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">Image</span><span class="font-black text-slate-950" x-text="selectedImage?.name || '—'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">پلن</span><span class="font-black text-slate-950" x-text="selectedBundle?.name || 'Custom'"></span></div>
                    <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">منابع</span><span class="font-black text-slate-950" dir="ltr" x-text="`${form.cpu_cores} CPU / ${form.ram_gb}GB / ${form.disk_gb}GB`"></span></div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">هزینه ماهانه تقریبی</p>
                        <p class="mt-2 text-2xl font-black text-slate-950" x-text="selectedBundle?.price || 'PAYG'"></p>
                    </div>
                </div>
                <button type="button" @click="$refs.createForm.submit()" class="mt-5 w-full rounded-lg bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">
                    ثبت درخواست ساخت
                </button>
                <p class="mt-3 text-xs leading-6 text-slate-500">IP به صورت خودکار از Pool آزاد تخصیص داده می‌شود و وضعیت Provisioning در صفحه سرورها نمایش داده می‌شود.</p>
            </div>
        </aside>
    </section>

    <script>
    function customerVmCreate(config) {
        return {
            bundles: config.bundles,
            images: config.images,
            form: {
                cloud_image_id: @js((string) old('cloud_image_id', '')),
                vm_bundle_id: @js((string) old('vm_bundle_id', '')),
                cpu_cores: @js((int) old('cpu_cores', 2)),
                ram_gb: @js((int) old('ram_gb', 4)),
                disk_gb: @js((int) old('disk_gb', 50)),
                login_username: @js(old('login_username', 'ubuntu')),
            },
            get selectedImage() { return this.images.find((image) => String(image.id) === String(this.form.cloud_image_id)); },
            get selectedBundle() { return this.bundles.find((bundle) => String(bundle.id) === String(this.form.vm_bundle_id)); },
            applyImage() {
                if (!this.selectedImage) return;
                this.form.login_username = this.selectedImage.default_username || 'ubuntu';
                this.form.cpu_cores = Math.max(Number(this.form.cpu_cores || 0), Number(this.selectedImage.min_cpu_cores));
                this.form.ram_gb = Math.max(Number(this.form.ram_gb || 0), Number(this.selectedImage.min_ram_gb));
                this.form.disk_gb = Math.max(Number(this.form.disk_gb || 0), Number(this.selectedImage.min_disk_gb));
            },
            applyBundle() {
                if (!this.selectedBundle) return;
                this.form.cpu_cores = this.selectedBundle.cpu_cores;
                this.form.ram_gb = this.selectedBundle.ram_gb;
                this.form.disk_gb = this.selectedBundle.disk_gb;
            },
        };
    }
    </script>
@endsection
