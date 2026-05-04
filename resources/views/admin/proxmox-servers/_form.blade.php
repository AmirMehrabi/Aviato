@csrf

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-black text-slate-700">نام سرور / کلاستر</span>
                <input name="name" value="{{ old('name', $server->name) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" required>
                @error('name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">نام کلاستر</span>
                <input name="cluster_name" value="{{ old('cluster_name', $server->cluster_name) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#105D52] focus:outline-none">
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">دیتاسنتر</span>
                <input name="datacenter" value="{{ old('datacenter', $server->datacenter) }}" placeholder="THR-1" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left focus:border-[#105D52] focus:outline-none">
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">محیط</span>
                <select name="environment" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none">
                    @foreach (['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development', 'dr' => 'Disaster Recovery'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('environment', $server->environment ?: 'production') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block md:col-span-2">
                <span class="text-sm font-black text-slate-700">آدرس API</span>
                <input name="host" value="{{ old('host', $server->host) }}" placeholder="pve01.example.com" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" required>
                @error('host') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">پورت</span>
                <input name="port" type="number" min="1" max="65535" value="{{ old('port', $server->port ?: 8006) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" required>
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">Realm</span>
                <input name="realm" value="{{ old('realm', $server->realm ?: 'pam') }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" required>
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">نام کاربری</span>
                <input name="username" value="{{ old('username', $server->username) }}" placeholder="root or root@pam" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" required>
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">رمز عبور</span>
                <input name="password" type="password" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" @if(! $server->exists) required @endif>
                <span class="mt-1 block text-xs text-slate-500">در ویرایش، خالی بگذارید تا تغییر نکند.</span>
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">API Token ID</span>
                <input name="api_token_id" value="{{ old('api_token_id', $server->api_token_id) }}" placeholder="automation" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none">
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">API Token Secret</span>
                <input name="api_token_secret" type="password" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none">
                <span class="mt-1 block text-xs text-slate-500">برای احراز هویت توکنی، token id و secret را وارد کنید.</span>
            </label>

            <label class="block md:col-span-2">
                <span class="text-sm font-black text-slate-700">برچسب‌ها</span>
                <input name="tags" value="{{ old('tags', implode(', ', $server->tags ?? [])) }}" placeholder="ssd, iran, high-memory" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none">
            </label>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">رفتار همگام‌سازی</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">اگر Proxmox آفلاین باشد، تغییرات در پنل ذخیره و در وضعیت pending نگه داشته می‌شود.</p>
            <div class="mt-5 space-y-3">
                <label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold">
                    <span>فعال</span>
                    <span>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $server->is_active ?? true))>
                    </span>
                </label>
                <label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold">
                    <span>تأیید TLS</span>
                    <span>
                        <input type="hidden" name="verify_tls" value="0">
                        <input type="checkbox" name="verify_tls" value="1" @checked(old('verify_tls', $server->verify_tls ?? true))>
                    </span>
                </label>
                <label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold">
                    <span>Maintenance Mode</span>
                    <span>
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $server->maintenance_mode ?? false))>
                    </span>
                </label>
                <label class="flex items-center justify-between gap-3 rounded-lg bg-[#F1F7F5] p-3 text-sm font-bold text-[#105D52]">
                    <span>بعد از ذخیره sync کن</span>
                    <input type="checkbox" name="sync_now" value="1" @checked(old('sync_now', false))>
                </label>
            </div>
        </div>

        <button class="w-full rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0D4C44]">ذخیره سرور</button>
        <a href="{{ route('admin.proxmox-servers.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت</a>
    </aside>
</div>
