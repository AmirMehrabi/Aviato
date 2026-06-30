@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'انتقال مالکیت ماشین')
@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="{
        toCustomerId: @js((string) old('to_customer_id', '')),
        toProjectId: @js((string) old('to_project_id', '')),
        customerProjects: @js($customers->map(fn ($customer) => [
            'id' => (string) $customer->id,
            'projects' => $customer->ownedProjects->map(fn ($project) => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'is_default' => (bool) $project->is_default,
            ])->values(),
        ])->values()),
        get projectsForCustomer() {
            return this.customerProjects.find((customer) => customer.id === String(this.toCustomerId))?.projects || [];
        },
        syncProject() {
            if (!this.projectsForCustomer.find((project) => project.id === String(this.toProjectId))) {
                this.toProjectId = this.projectsForCustomer[0]?.id || '';
            }
        },
    }"
    x-init="syncProject()"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6">
        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="text-sm font-bold text-[#0069FF] hover:underline">
            ← بازگشت به VM
        </a>
    </div>

    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative">
            <p class="text-sm font-bold text-white/60">انتقال مالکیت ماشین</p>
            <h1 class="mt-1 text-2xl font-black md:text-4xl" dir="ltr">{{ $vm->display_name }}</h1>
            <p class="mt-1 text-sm font-bold text-white/50" dir="ltr">{{ $vm->name }}</p>
            <p class="mt-3 leading-8 text-white/75">
                مالک فعلی: <span class="font-black">{{ $vm->customer?->name }}</span> ·
                فضای کاری فعلی: <span class="font-black">{{ $vm->project?->name }}</span>
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_450px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">انتقال به مشتری و فضای کاری جدید</h2>
            <p class="mt-2 text-sm text-slate-600">
                این عملیات مالکیت ماشین و پرداخت‌های بعدی آن را به مشتری و فضای کاری انتخاب‌شده منتقل می‌کند.
            </p>

            @if($vm->isDeleting() || $vm->isDeleted())
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
                    این ماشین در حال حذف است یا قبلا حذف شده و قابل انتقال نیست.
                </div>
            @elseif($vm->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING)
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    این ماشین هنوز در حال ساخت است. بعد از پایان ساخت می‌توانید آن را منتقل کنید.
                </div>
            @elseif($vm->pendingUpgradeOrders()->exists())
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    این ماشین سفارش ارتقای در حال انجام دارد. بعد از پایان ارتقا می‌توانید آن را منتقل کنید.
                </div>
            @else
                <form method="POST" action="{{ route('admin.virtual-machines.transfer', $vm) }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-bold text-slate-700">مشتری مقصد *</label>
                        <select name="to_customer_id" x-model="toCustomerId" @change="toProjectId = ''; syncProject()" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-[#0069FF]/20">
                            <option value="">انتخاب مشتری</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('to_customer_id') == $customer->id)>
                                    {{ $customer->name }} ({{ $customer->email ?: $customer->phone }})
                                </option>
                            @endforeach
                        </select>
                        @error('to_customer_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">فضای کاری مقصد *</label>
                        <select name="to_project_id" x-model="toProjectId" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-[#0069FF]/20">
                            <option value="">ابتدا مشتری مقصد را انتخاب کنید</option>
                            <template x-for="project in projectsForCustomer" :key="project.id">
                                <option :value="project.id" x-text="project.name + (project.is_default ? ' - پیش‌فرض' : '')"></option>
                            </template>
                        </select>
                        @error('to_project_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-500">هزینه‌های بعدی این ماشین با مالک فضای کاری مقصد خواهد بود.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">یادداشت انتقال (اختیاری)</label>
                        <textarea name="notes" rows="3" placeholder="توضیح کوتاه برای سابقه پشتیبانی..." class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-[#0069FF]/20">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-500">این یادداشت در تاریخچه انتقال ذخیره می‌شود.</p>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <h3 class="font-black text-amber-900">اثر انتقال</h3>
                        <ul class="mt-3 space-y-2 text-sm text-amber-800">
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>مالکیت ماشین به مشتری انتخاب‌شده منتقل می‌شود.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>ماشین داخل فضای کاری انتخاب‌شده قرار می‌گیرد.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>مبلغ محاسبه‌نشده فعلی ({{ $money->format($vm->unbilled_amount ?? 0) }}) به انتقال ثبت می‌شود.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>هزینه‌های بعدی با مالک فضای کاری مقصد است.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>داده‌ها، بکاپ‌ها و تنظیمات ماشین تغییر نمی‌کند.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>سابقه کامل انتقال نگهداری می‌شود.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <input type="checkbox" name="confirm_transfer" id="confirm_transfer" value="1" required class="mt-1 size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                        <label for="confirm_transfer" class="text-sm font-bold text-slate-700">
                            تایید می‌کنم که می‌خواهم این ماشین به مشتری و فضای کاری انتخاب‌شده منتقل شود. می‌دانم که مسئول پرداخت تغییر می‌کند.
                        </label>
                    </div>
                    @error('confirm_transfer')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex gap-3">
                        <button type="submit" class="rounded-lg bg-[#0069FF] px-6 py-3 text-sm font-black text-white hover:bg-[#0052CC]">
                            انتقال ماشین
                        </button>
                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-lg border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">
                            لغو
                        </a>
                    </div>
                </form>
            @endif
        </section>

        <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black">جزئیات فعلی ماشین</h2>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">وضعیت:</span>
                        <span class="font-black">{{ \App\Support\AdminUi::status($vm->status) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">وضعیت ساخت:</span>
                        <span class="font-black">{{ \App\Support\AdminUi::status($vm->provisioning_status) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">منابع:</span>
                        <span class="font-black">{{ $vm->cpu_cores }}C / {{ $vm->ram_gb }}GB / {{ $vm->disk_gb }}GB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">هزینه ماهانه:</span>
                        <span class="font-black">{{ $money->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">مبلغ محاسبه‌نشده:</span>
                        <span class="font-black">{{ $money->format($vm->unbilled_amount ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black">مالکیت فعلی</h2>
                <div class="mt-5 space-y-3 text-sm">
                    <div>
                        <p class="font-bold text-slate-500">مشتری مسئول پرداخت:</p>
                        <p class="mt-1 font-black">{{ $vm->customer?->name }}</p>
                        <p class="text-xs text-slate-500">{{ $vm->customer?->email }}</p>
                    </div>
                    <div>
                        <p class="font-bold text-slate-500">فضای کاری:</p>
                        <p class="mt-1 font-black">{{ $vm->project?->name }}</p>
                    </div>
                    <div>
                        <p class="font-bold text-slate-500">ساخته‌شده توسط:</p>
                        <p class="mt-1 font-black">{{ $vm->creator?->name }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @if($transfers->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">تاریخچه انتقال</h2>
            <p class="mt-2 text-sm text-slate-600">سابقه کامل انتقال مالکیت این ماشین.</p>

            <div class="mt-5 space-y-4">
                @foreach($transfers as $transfer)
                    <div class="rounded-lg border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <span class="rounded-lg bg-[#EBF3FF] px-3 py-1 text-xs font-black text-[#0069FF]">
                                        انتقال #{{ $transfer->id }}
                                    </span>
                                    <span class="text-sm text-slate-500">
                                        {{ $transfer->completed_at?->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <span class="font-black">{{ $transfer->fromCustomer?->name }}</span>
                                    @if($transfer->fromProject)
                                        <span class="text-xs text-slate-500">({{ $transfer->fromProject->name }})</span>
                                    @endif
                                    <span class="text-slate-400">→</span>
                                    <span class="font-black">{{ $transfer->toCustomer?->name }}</span>
                                    @if($transfer->toProject)
                                        <span class="text-xs text-slate-500">({{ $transfer->toProject->name }})</span>
                                    @endif
                                </div>
                                @if($transfer->notes)
                                    <p class="mt-2 text-sm text-slate-600">{{ $transfer->notes }}</p>
                                @endif
                                <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                                    <span>ثبت‌کننده: <span class="font-bold">{{ $transfer->initiatedBy?->name }}</span></span>
                                    <span>مبلغ انتقال: <span class="font-bold">{{ $money->format($transfer->unbilled_amount_transferred) }}</span></span>
                                </div>
                            </div>
                        </div>

                        @if($transfer->snapshot_before || $transfer->snapshot_after)
                            <details class="mt-4">
                                <summary class="cursor-pointer text-xs font-bold text-slate-500 hover:text-slate-700">
                                    مشاهده جزئیات فنی
                                </summary>
                                <div class="mt-3 grid gap-4 md:grid-cols-2">
                                    @if($transfer->snapshot_before)
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-xs font-black text-slate-700">قبل از انتقال</p>
                                            <pre class="mt-2 overflow-x-auto text-xs text-slate-600">{{ json_encode($transfer->snapshot_before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    @if($transfer->snapshot_after)
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-xs font-black text-slate-700">بعد از انتقال</p>
                                            <pre class="mt-2 overflow-x-auto text-xs text-slate-600">{{ json_encode($transfer->snapshot_after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
