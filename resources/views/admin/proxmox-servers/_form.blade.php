@csrf

@php
    $configuredNodeEndpoints = collect($server->api_endpoints ?? [])
        ->filter(fn (mixed $endpoint, mixed $node): bool => is_string($node) && is_string($endpoint));
    $activeNodeNames = collect(data_get($server->remote_inventory, 'nodes', []))
        ->map(fn (array $node): mixed => $node['node'] ?? $node['name'] ?? null)
        ->filter()
        ->unique()
        ->sort()
        ->values();
    $oldNodeEndpoints = old('node_api_endpoints', []);
    $configuredNodeCredentials = collect($server->node_api_credentials ?? []);
    $oldNodeCredentials = old('node_api_credentials', []);
    $staleNodeNames = $configuredNodeEndpoints->keys()
        ->merge($configuredNodeCredentials->keys())
        ->unique()
        ->diff($activeNodeNames)
        ->sort()
        ->values();
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <x-form.input name="name" label="نام سرور / کلاستر" :value="$server->name" dir-ltr required />

            <x-form.input name="cluster_name" label="نام کلاستر" :value="$server->cluster_name" input-class="text-left" />

            <x-form.input name="datacenter" label="دیتاسنتر" :value="$server->datacenter" placeholder="THR-1" input-class="text-left" />

            <x-form.select
                name="environment"
                label="محیط"
                :options="['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development', 'dr' => 'Disaster Recovery']"
                :selected="$server->environment ?: 'production'"
            />

            <x-form.input
                name="host"
                label="آدرس API"
                :value="$server->host"
                placeholder="pve01.example.com"
                wrapper-class="block md:col-span-2"
                dir-ltr
                required
            />

            <x-form.input
                name="port"
                label="پورت"
                type="number"
                :value="$server->port ?: 8006"
                min="1"
                max="65535"
                dir-ltr
                required
            />

            <x-form.input name="realm" label="Realm" :value="$server->realm ?: 'pam'" dir-ltr required />

            <x-form.input
                name="username"
                label="نام کاربری"
                :value="$server->username"
                placeholder="root or root@pam"
                dir-ltr
                required
            />

            <x-form.input
                name="password"
                label="رمز عبور"
                type="password"
                help="در ویرایش، خالی بگذارید تا تغییر نکند."
                dir-ltr
                :required="! $server->exists"
            />

            <x-form.input
                name="api_token_id"
                label="API Token ID"
                :value="$server->api_token_id"
                placeholder="root@pam!avapardaz or avapardaz"
                help="می‌توانید Final Token ID کامل را وارد کنید؛ اگر فقط نام توکن را وارد کنید، برنامه username و realm را اضافه می‌کند."
                dir-ltr
            />

            <x-form.input
                name="api_token_secret"
                label="API Token Secret"
                type="password"
                help="برای احراز هویت توکنی، token id و secret را وارد کنید."
                dir-ltr
            />

            <x-form.input
                name="tags"
                label="برچسب‌ها"
                :value="implode(', ', $server->tags ?? [])"
                placeholder="ssd, iran, high-memory"
                wrapper-class="block md:col-span-2"
                dir-ltr
            />

            <x-form.input name="cpu_threshold_percent" type="number" label="حد CPU (%)" :value="$server->cpu_threshold_percent ?: 80" min="1" max="100" />
            <x-form.input name="ram_threshold_percent" type="number" label="حد RAM (%)" :value="$server->ram_threshold_percent ?: 85" min="1" max="100" />
            <x-form.input name="disk_threshold_percent" type="number" label="حد Storage (%)" :value="$server->disk_threshold_percent ?: 80" min="1" max="100" />
            <div class="md:col-span-2">
                <span class="text-sm font-black text-slate-700">آدرس API اختصاصی nodeها</span>
                <p class="mt-1 text-xs leading-6 text-slate-500">پس از شناسایی nodeها، آدرس مدیریت هرکدام را وارد کنید. درخواست‌های همان node مستقیماً به این آدرس ارسال می‌شوند.</p>
                @if ($activeNodeNames->isEmpty())
                    <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-xs font-bold text-slate-500">ابتدا سرور را ذخیره و Sync کنید تا nodeها شناسایی شوند.</p>
                @else
                    <div class="mt-3 grid gap-3">
                        @foreach ($activeNodeNames as $nodeName)
                            <label class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[120px_minmax(0,1fr)] sm:items-center">
                                <span class="font-mono text-sm font-bold text-slate-700" dir="ltr">{{ $nodeName }}</span>
                                <input
                                    type="url"
                                    name="node_api_endpoints[{{ $nodeName }}]"
                                    value="{{ $oldNodeEndpoints[$nodeName] ?? $configuredNodeEndpoints->get($nodeName) }}"
                                    placeholder="https://172.19.19.3:8006"
                                    dir="ltr"
                                    class="w-full rounded-lg border border-slate-200 px-4 py-3 text-left text-sm focus:border-[#0069FF] focus:outline-none"
                                >
                            </label>
                            <div class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[120px_minmax(0,1fr)_minmax(0,1fr)] sm:items-center">
                                <span class="font-mono text-sm font-bold text-slate-700" dir="ltr">{{ $nodeName }} Auth</span>
                                <input
                                    type="text"
                                    name="node_api_credentials[{{ $nodeName }}][token_id]"
                                    value="{{ data_get($oldNodeCredentials, $nodeName.'.token_id', data_get($configuredNodeCredentials, $nodeName.'.token_id')) }}"
                                    placeholder="root@pam!panel"
                                    dir="ltr"
                                    autocomplete="off"
                                    class="w-full rounded-lg border border-slate-200 px-4 py-3 text-left text-sm focus:border-[#0069FF] focus:outline-none"
                                >
                                <input
                                    type="password"
                                    name="node_api_credentials[{{ $nodeName }}][token_secret]"
                                    placeholder="{{ data_get($configuredNodeCredentials, $nodeName.'.token_secret') ? 'برای حفظ مقدار فعلی خالی بگذارید' : 'API Token Secret' }}"
                                    dir="ltr"
                                    autocomplete="new-password"
                                    class="w-full rounded-lg border border-slate-200 px-4 py-3 text-left text-sm focus:border-[#0069FF] focus:outline-none"
                                >
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($staleNodeNames->isNotEmpty())
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-black text-amber-900">nodeهای حذف‌شده از آخرین Sync</span>
                            <span class="text-xs font-bold text-amber-700">برای حذف تنظیمات ذخیره‌شده، گزینه حذف را فعال کنید.</span>
                        </div>
                        <div class="mt-3 grid gap-3">
                            @foreach ($staleNodeNames as $nodeName)
                                <label class="grid gap-3 rounded-lg border border-amber-200 bg-white p-3 sm:grid-cols-[120px_minmax(0,1fr)_auto] sm:items-center">
                                    <span class="font-mono text-sm font-bold text-slate-700" dir="ltr">{{ $nodeName }}</span>
                                    <span class="min-w-0 text-left font-mono text-xs font-bold text-slate-500" dir="ltr">
                                        {{ $configuredNodeEndpoints->get($nodeName) ?: data_get($configuredNodeCredentials, $nodeName.'.token_id', 'بدون endpoint') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2 text-xs font-black text-red-700">
                                        <input
                                            type="checkbox"
                                            name="remove_stale_nodes[]"
                                            value="{{ $nodeName }}"
                                            class="rounded border-red-300 text-red-600 focus:ring-red-500"
                                        >
                                        حذف تنظیمات
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">رفتار همگام‌سازی</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">اگر Proxmox آفلاین باشد، تغییرات در پنل ذخیره و در وضعیت pending نگه داشته می‌شود.</p>
            <div class="mt-5 space-y-3">
                <x-form.checkbox name="is_active" label="فعال" :checked="$server->is_active ?? true" />
                <x-form.checkbox name="verify_tls" label="تأیید TLS" :checked="$server->verify_tls ?? true" />
                <x-form.checkbox name="maintenance_mode" label="Maintenance Mode" :checked="$server->maintenance_mode ?? false" />
                <x-form.checkbox
                    name="sync_now"
                    label="بعد از ذخیره sync کن"
                    :checked="false"
                    :include-hidden="false"
                    wrapper-class="flex items-center justify-between gap-3 rounded-lg bg-[#EBF3FF] p-3 text-sm font-bold text-[#0069FF]"
                />
            </div>
        </div>

        <button class="w-full rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ذخیره سرور</button>
        <a href="{{ route('admin.proxmox-servers.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت</a>
    </aside>
</div>
