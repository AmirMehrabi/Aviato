@if ($canManageServer && session()->has('password_reset_password'))
    @php($resetPassword = session()->pull('password_reset_password'))
    <section class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 p-5" x-data="{ copied: false }" role="status">
        <h2 class="font-black text-blue-950">رمز عبور جدید — همین حالا ذخیره کنید</h2>
        <p class="mt-2 text-sm leading-7 text-blue-900">این رمز فقط همین‌بار نمایش داده می‌شود. پس از تکمیل راه‌اندازی مجدد و اجرای CloudInit، با آن وارد شوید.</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <code dir="ltr" class="select-all break-all rounded-xl bg-white px-4 py-3 text-lg font-bold">{{ $resetPassword }}</code>
            <button type="button" @click="navigator.clipboard.writeText(@js($resetPassword)).then(() => copied = true).catch(() => copied = false)" class="rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-bold text-blue-800" x-text="copied ? 'کپی شد' : 'کپی رمز'">کپی رمز</button>
        </div>
    </section>
@endif
@if ($server->password_reset_status === 'pending')
    <div class="mb-5 rounded-xl border p-4 text-sm leading-7" x-data="vmPasswordResetProgress(@js(route('customer.servers.statuses', ['ids' => [$server->uuid]], false)), @js($server->uuid))"
        :class="state === 'failed' ? 'border-red-200 bg-red-50 text-red-800' : (state === 'succeeded' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900')" role="status" aria-live="polite">
        <p x-show="state === 'pending'"><strong>بازنشانی رمز در حال انجام است.</strong> سرور راه‌اندازی مجدد خواهد شد و اتصال موقتاً قطع می‌شود. وضعیت به‌صورت خودکار بررسی می‌شود.</p>
        <p x-show="state === 'succeeded'" x-cloak>رمز جدید به CloudInit ارسال و سرور راه‌اندازی مجدد شد. آماده شدن ورود ممکن است چند دقیقه طول بکشد.</p>
        <p x-show="state === 'failed'" x-cloak>بازنشانی رمز یا راه‌اندازی مجدد کامل نشد. ممکن است رمز جدید اعمال شده یا سرور خاموش باشد. وضعیت سرور را بررسی کنید و در صورت مشکل با پشتیبانی تماس بگیرید.</p>
        <a href="{{ route('customer.servers.show', $server, false) }}" class="font-black underline">بررسی وضعیت و تازه‌سازی صفحه</a>
    </div>
    @push('scripts')
        <script>
            window.vmPasswordResetProgress = (url, vmId) => ({
                state: 'pending',
                timer: null,
                init() { this.check(); },
                destroy() { clearTimeout(this.timer); },
                async check() {
                    try {
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (response.ok) {
                            const data = await response.json();
                            const vm = data.servers.find(server => server.id === vmId);
                            if (vm && ['pending', 'succeeded', 'failed'].includes(vm.password_reset_status)) {
                                this.state = vm.password_reset_status;
                            }
                        }
                    } catch (error) {
                        // Keep the manual refresh link available when polling fails.
                    }
                    if (this.state === 'pending') this.timer = setTimeout(() => this.check(), 5000);
                },
            });
        </script>
    @endpush
@elseif ($server->password_reset_status === 'failed')
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-800" role="alert">
        بازنشانی رمز یا راه‌اندازی مجدد کامل نشد. ممکن است رمز جدید اعمال شده یا سرور خاموش باشد. وضعیت سرور را بررسی کنید و در صورت مشکل با پشتیبانی تماس بگیرید.
    </div>
@elseif ($server->password_reset_status === 'succeeded')
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-7 text-emerald-900" role="status">
        رمز جدید به CloudInit ارسال و سرور راه‌اندازی مجدد شد. آماده شدن ورود ممکن است چند دقیقه طول بکشد. اگر ورود ممکن نشد، با پشتیبانی تماس بگیرید.
    </div>
@endif
