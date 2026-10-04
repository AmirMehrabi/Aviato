@php
    $canResetPassword = $canManageServer && ! $server->isActionLocked() && $server->isRunning()
        && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY
        && ! $server->pendingUpgradeOrders()->exists() && $server->proxmoxServer && $server->node && $server->vmid;
@endphp
@if ($canManageServer && $server->supportsCloudInitPasswordReset())
    <details class="mt-5 rounded-xl border border-slate-200" @if($errors->has('confirm_reboot')) open @endif>
        <summary class="cursor-pointer px-4 py-3 text-sm font-black text-[#0069FF]">بازنشانی رمز عبور</summary>
        <form method="POST" action="{{ route('customer.servers.reset-password', $server, false) }}" class="space-y-4 border-t border-slate-100 p-4" x-data="{ confirmed: false, submitting: false }" @submit="submitting = true">
            @csrf
            <p class="text-sm leading-7 text-slate-600">یک رمز امن جدید برای کاربر <b dir="ltr">{{ $server->login_username ?: $server->cloudImage?->default_username ?: 'root' }}</b> ساخته می‌شود. فایل‌ها و کلیدهای SSH شما حفظ می‌شوند.</p>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-7 text-amber-900">
                <strong class="block">سرور راه‌اندازی مجدد خواهد شد.</strong>
                سرور خاموش و دوباره روشن می‌شود؛ اتصال SSH و سرویس‌ها موقتاً قطع خواهند شد. پیش از ادامه، کارهای باز خود را ذخیره کنید.
            </div>
            @if (! $server->retain_login_password)
                <p class="text-xs leading-6 text-slate-500">رمز جدید فقط یک‌بار در صفحه بعد نمایش داده می‌شود. آن را در محل امن ذخیره کنید؛ بعداً قابل بازیابی نیست.</p>
            @endif
            @if ($canResetPassword)
                <label class="flex items-start gap-2 text-sm leading-6 text-slate-700">
                    <input type="checkbox" name="confirm_reboot" value="1" required x-model="confirmed" class="mt-1 rounded border-slate-300">
                    تأیید می‌کنم که سرور راه‌اندازی مجدد و اتصال موقتاً قطع شود.
                </label>
                @error('confirm_reboot') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" :disabled="!confirmed || submitting" class="rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'در حال ثبت درخواست…' : 'ساخت رمز جدید و راه‌اندازی مجدد'">ساخت رمز جدید و راه‌اندازی مجدد</button>
            @else
                <p class="text-sm text-slate-500">بازنشانی رمز فقط زمانی در دسترس است که سرور روشن و آماده باشد و عملیات دیگری در حال اجرا نباشد.</p>
            @endif
        </form>
    </details>
@endif
