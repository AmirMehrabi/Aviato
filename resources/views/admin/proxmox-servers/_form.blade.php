@csrf

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
                    wrapper-class="flex items-center justify-between gap-3 rounded-lg bg-[#F1F7F5] p-3 text-sm font-bold text-[#105D52]"
                />
            </div>
        </div>

        <button class="w-full rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0D4C44]">ذخیره سرور</button>
        <a href="{{ route('admin.proxmox-servers.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت</a>
    </aside>
</div>
