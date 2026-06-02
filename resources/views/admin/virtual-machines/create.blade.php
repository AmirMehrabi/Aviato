@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'Create Cloud VPS')

@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="cloudVmCreate({
        bundles: @js($bundles->map(fn ($bundle) => [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'cpu_cores' => $bundle->cpu_cores,
            'ram_gb' => $bundle->ram_gb,
            'disk_gb' => $bundle->disk_gb,
            'price' => $money->format($bundle->monthly_price),
        ])->values()),
        images: @js($cloudImages->map(fn ($image) => [
            'id' => $image->id,
            'name' => $image->name,
            'server' => $image->proxmoxServer?->name,
            'node' => $image->node,
            'template_vmid' => $image->template_vmid,
            'default_username' => $image->default_username,
            'cloud_init_enabled' => $image->cloud_init_enabled,
            'min_cpu_cores' => $image->min_cpu_cores,
            'min_ram_gb' => $image->min_ram_gb,
            'min_disk_gb' => $image->min_disk_gb,
            'allowed_bundle_ids' => $image->allowedBundles->pluck('id')->values()->all(),
        ])->values()),
    })"
>
    @if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-bold text-white/60">Cloud-init Provisioning</p>
                <h1 class="mt-2 text-3xl font-black">ساخت VPS از Cloud Image</h1>
                <p class="mt-3 max-w-3xl leading-8 text-white/75">Template VMID از کاتالوگ Cloud Images انتخاب می‌شود؛ IP از Pool رزرو و Provisioning در Queue انجام می‌شود.</p>
            </div>
            <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E]">بازگشت</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.virtual-machines.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        <div class="grid gap-5 md:grid-cols-2">
            <x-form.select name="customer_id" label="مشتری" :selected="$selectedCustomerId" :options="$customers->prepend('انتخاب مشتری', '')" />
            <label>
                <span class="text-sm font-black text-slate-700">Project</span>
                <select name="project_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    <option value="">Default Project مشتری</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                            {{ $project->name }} - {{ $project->owner?->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">اگر خالی باشد VM داخل Default Project مشتری انتخاب شده ساخته می‌شود.</p>
                @error('project_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="text-sm font-black text-slate-700">Cloud Image</span>
                <select name="cloud_image_id" x-model="form.cloud_image_id" @change="applyImage()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    <option value="">انتخاب Image</option>
                    <template x-for="image in images" :key="image.id">
                        <option :value="image.id" x-text="`${image.name} / ${image.server} / ${image.node} / template ${image.template_vmid}`"></option>
                    </template>
                </select>
                @error('cloud_image_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <x-form.input name="name" label="نام VM در Proxmox" value="" dir-ltr />
            <div x-show="cloudInitEnabled">
                <x-form.input name="hostname" label="Hostname" value="" dir-ltr x-bind:disabled="!cloudInitEnabled" />
            </div>

            <label class="md:col-span-2">
                <span class="text-sm font-black text-slate-700">باندل سخت‌افزاری</span>
                <select name="vm_bundle_id" x-model="form.vm_bundle_id" @change="applyBundle()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    <option value="">Custom منابع دستی</option>
                    <template x-for="bundle in visibleBundles" :key="bundle.id">
                        <option :value="bundle.id" x-text="`${bundle.name} - ${bundle.cpu_cores} CPU / ${bundle.ram_gb}GB RAM / ${bundle.disk_gb}GB - ${bundle.price}`"></option>
                    </template>
                </select>
                <p x-show="selectedImage && !visibleBundles.length" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold leading-6 text-amber-900">
                    برای این Image هیچ پلن فعالی تعریف نشده است؛ می‌توانید منابع را به صورت دستی وارد کنید.
                </p>
            </label>

            <x-form.input name="cpu_cores" type="number" label="CPU Core" value="2" x-model="form.cpu_cores" />
            <x-form.input name="ram_gb" type="number" label="RAM (GB)" value="4" x-model="form.ram_gb" />
            <x-form.input name="disk_gb" type="number" label="Disk (GB)" value="50" x-model="form.disk_gb" />
            <div x-show="cloudInitEnabled">
                <x-form.input name="login_username" label="Cloud-init Username" value="ubuntu" dir-ltr x-model="form.login_username" x-bind:disabled="!cloudInitEnabled" />
            </div>
            <div x-show="cloudInitEnabled">
                <x-form.input name="login_password" type="password" label="Password" help="اختیاری؛ اگر SSH key هم خالی باشد یک password امن ساخته و یک‌بار نمایش داده می‌شود." x-bind:disabled="!cloudInitEnabled" />
            </div>
            <label x-show="cloudInitEnabled">
                <span class="text-sm font-black text-slate-700">SSH Public Key</span>
                <textarea name="ssh_public_key" rows="4" dir="ltr" :disabled="!cloudInitEnabled" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#0069FF] focus:outline-none">{{ old('ssh_public_key') }}</textarea>
                @error('ssh_public_key') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>
            <div x-show="!cloudInitEnabled" class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                این template بدون CloudInit است؛ hostname، username، password و SSH key تنظیم نمی‌شوند.
            </div>

            <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="start_after_create" value="1" checked class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">بعد از Provisioning روشن شود</span></label>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="onboot" value="1" class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">Start on boot</span></label>
        </div>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600" x-show="selectedImage">
            <span class="font-black text-slate-900">Minimum selected image:</span>
            <span x-text="`${selectedImage.min_cpu_cores} CPU / ${selectedImage.min_ram_gb}GB RAM / ${selectedImage.min_disk_gb}GB Disk`"></span>
        </div>

        <div class="mt-6 flex gap-3">
            <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Queue Provisioning</button>
            <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a>
        </div>
    </form>
</div>

<script>
function cloudVmCreate(config) {
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
        get selectedImage() {
            return this.images.find((image) => String(image.id) === String(this.form.cloud_image_id));
        },
        get visibleBundles() {
            if (!this.selectedImage) return [];
            const allowed = new Set((this.selectedImage.allowed_bundle_ids || []).map((id) => String(id)));
            return this.bundles.filter((bundle) => allowed.has(String(bundle.id)));
        },
        get cloudInitEnabled() {
            return this.selectedImage ? Boolean(this.selectedImage.cloud_init_enabled) : true;
        },
        applyImage() {
            if (!this.selectedImage) return;
            this.form.login_username = this.cloudInitEnabled ? (this.selectedImage.default_username || 'ubuntu') : '';
            this.form.cpu_cores = Math.max(Number(this.form.cpu_cores || 0), Number(this.selectedImage.min_cpu_cores));
            this.form.ram_gb = Math.max(Number(this.form.ram_gb || 0), Number(this.selectedImage.min_ram_gb));
            this.form.disk_gb = Math.max(Number(this.form.disk_gb || 0), Number(this.selectedImage.min_disk_gb));
            this.syncBundleSelection();
        },
        applyBundle() {
            const bundle = this.bundles.find((item) => String(item.id) === String(this.form.vm_bundle_id));
            if (!bundle) return;
            this.form.cpu_cores = bundle.cpu_cores;
            this.form.ram_gb = bundle.ram_gb;
            this.form.disk_gb = bundle.disk_gb;
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
        },
    };
}
</script>
@endsection
