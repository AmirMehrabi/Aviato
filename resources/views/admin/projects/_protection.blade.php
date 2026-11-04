@adminRoute('admin.projects.protection.update')
@php
    $protectionDefaults = app(\App\Services\WorkspaceProtectionSettings::class)->defaults();
    $protectionFields = [
        'unverified_customer_vm_limit' => ['سقف VM مالک تأییدنشده', 'صفر یعنی امکان ساخت VM وجود ندارد. سقف سفارشی فقط VMهای همین فضای کاری را می‌شمارد.', 0, 1000000, 1],
        'verified_customer_vm_limit' => ['سقف VM مالک تأییدشده', 'صفر یعنی نامحدود. سقف سفارشی فقط VMهای همین فضای کاری را می‌شمارد.', 0, 1000000, 1],
        'deleted_vm_cooldown_days' => ['روزهای نگهداری سهمیه VM حذف‌شده', 'برای مالک تأییدنشده؛ صفر یعنی آزادسازی فوری سهمیه.', 0, 3650, 1],
        'wallet_retention_days' => ['مهلت شارژ پس از اتمام موجودی (روز)', 'فقط برای مهلت‌های جدید اعمال می‌شود؛ تاریخ حذف فعلی ماشین‌ها تغییر نمی‌کند.', 1, 365, 1],
        'wallet_shutdown_percentage' => ['آستانه خاموشی در بدهی (درصد)', 'درصدی از هزینه ماهانه کل مالک؛ با رسیدن بدهی به این آستانه، VMهای این فضای کاری خاموش می‌شوند.', 1, 100, 1],
        'vm_rebuild_fee_multiplier_percentage' => ['درصد هزینه بازسازی', 'درصدی از هزینه ساخت اولیه؛ صفر یعنی بازسازی رایگان.', 0, 100, 0.01],
    ];
@endphp
<details @if(old('protection') || session('protection_saved')) open @endif id="protection" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <summary class="cursor-pointer text-lg font-black text-slate-950">
        محافظت فضای کاری
        <span class="mr-2 text-xs font-bold text-slate-500">{{ count($project->protection_overrides ?? []) ? \App\Support\PersianDigits::convert((string) count($project->protection_overrides)).' مقدار سفارشی' : 'همه موارد از پیش‌فرض سراسری' }}</span>
    </summary>
    @if(session('protection_saved'))
        <p role="status" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm font-bold text-blue-900">تنظیمات محافظت فضای کاری ذخیره شد.</p>
    @endif
    <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="mt-2 text-sm leading-7 text-slate-500">فقط مواردی را که نیاز دارید سفارشی کنید. سایر موارد با تغییر تنظیمات سراسری به‌روز می‌شوند.</p>
        </div>
        @adminRoute('admin.settings.section')
        <a href="{{ route('admin.settings.section', 'protection') }}" class="text-sm font-bold text-[#0069FF]">تنظیمات سراسری</a>
        @endadminRoute
    </div>
    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs leading-6 text-slate-600">کیف پول بین فضاهای کاری مالک مشترک است. سقف VM پیش‌فرض همچنان در سطح حساب محاسبه می‌شود؛ سقف سفارشی در سطح همین فضای کاری اعمال می‌شود.</p>
    <form method="POST" action="{{ route('admin.projects.protection.update', $project) }}" class="mt-5" x-data @submit="$el.querySelector('[type=submit]').disabled = true">
        @csrf
        @method('PATCH')
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($protectionFields as $key => [$label, $help, $min, $max, $step])
                @php
                    $mode = old("protection.$key.mode", isset($project->protection_overrides[$key]) ? 'custom' : 'default');
                    $value = old("protection.$key.value", $project->protection_overrides[$key] ?? $protectionDefaults[$key]);
                @endphp
                <div class="rounded-lg border border-slate-200 p-4" x-data="{ mode: @js($mode) }" @protection-reset.window="mode = 'default'">
                    <label for="protection-mode-{{ $key }}" class="block text-sm font-black text-slate-900">{{ $label }}</label>
                    <select id="protection-mode-{{ $key }}" name="protection[{{ $key }}][mode]" x-model="mode" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
                        <option value="default" @selected($mode === 'default')>پیش‌فرض سراسری ({{ \App\Support\PersianDigits::convert((string) $protectionDefaults[$key]) }})</option>
                        <option value="custom" @selected($mode === 'custom')>مقدار سفارشی برای این فضای کاری</option>
                    </select>
                    <label for="protection-value-{{ $key }}" class="mt-3 block text-xs font-bold text-slate-500">مقدار سفارشی</label>
                    <input id="protection-value-{{ $key }}" name="protection[{{ $key }}][value]" type="number" value="{{ $value }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" dir="ltr" :disabled="mode !== 'custom'" :required="mode === 'custom'" aria-describedby="protection-help-{{ $key }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-100 disabled:text-slate-400">
                    <p id="protection-help-{{ $key }}" class="mt-2 text-xs leading-6 text-slate-500">{{ $help }}</p>
                    @error("protection.$key.value")<p role="alert" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
                    @error("protection.$key.mode")<p role="alert" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-4">
            <button type="submit" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white disabled:opacity-50">ذخیره تنظیمات محافظت</button>
            <button type="button" @click="$dispatch('protection-reset')" class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-bold text-slate-600">بازگشت همه به پیش‌فرض</button>
            <p class="text-xs text-slate-500">تغییرات پس از ذخیره اعمال می‌شوند.</p>
        </div>
    </form>
</details>
@endadminRoute
