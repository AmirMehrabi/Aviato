@adminAbility('wallet.manage')

    @if(auth('admin')->user()->is_active)<section class="mt-6 rounded-2xl border {{ $customer->auto_suspend_vms ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-950">محدودسازی خودکار سرویس‌ها</h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">در موجودی مؤثر صفر، شبکه ماشین‌های متصل به کیف پول قطع می‌شود. در حد بدهی تعیین‌شده در تنظیمات، ماشین‌های روشن خاموش می‌شوند. شارژ کیف پول شبکه را وصل می‌کند و ماشین‌هایی را که به‌دلیل بدهی خاموش شده‌اند روشن می‌کند.</p>
                <p class="mt-2 text-sm leading-7 text-slate-600">غیرفعال کردن این گزینه، محدودیت‌های قبلی سرویس‌ها را برطرف نمی‌کند.</p>
                <p class="mt-2 text-sm font-semibold {{ $customer->auto_suspend_vms ? 'text-amber-800' : 'text-emerald-800' }}">وضعیت: {{ $customer->auto_suspend_vms ? 'فعال' : 'غیرفعال' }}</p>
            </div>

@adminRoute('admin.customers.auto-suspension.update')
<form method="POST" action="{{ route('admin.customers.auto-suspension.update', $customer) }}" class="shrink-0">
                @csrf
                @method('PATCH')
                <input type="hidden" name="auto_suspend_vms" value="{{ $customer->auto_suspend_vms ? 0 : 1 }}">
                <button class="rounded-lg {{ $customer->auto_suspend_vms ? 'bg-amber-600' : 'bg-emerald-600' }} px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90">
                    {{ $customer->auto_suspend_vms ? 'غیرفعال کردن' : 'فعال کردن' }}
                </button>
            </form>
@endadminRoute

        </div>
    </section>@endif

@endadminAbility
