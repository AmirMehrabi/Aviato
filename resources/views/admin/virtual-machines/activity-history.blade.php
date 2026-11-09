<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="operations-heading">
    <h2 id="operations-heading" class="text-xl font-bold">درخواست‌های سرور</h2>
    <p class="mt-2 text-sm text-slate-600">وضعیت جاری به‌صورت خودکار بررسی می‌شود. برای دیدن آخرین تاریخچه، صفحه را تازه کنید.</p>
    <form method="GET" action="{{ route('admin.virtual-machines.show', $vm) }}#activities" class="mt-4 flex flex-wrap items-end gap-3">
        <div><label for="operation-status" class="mb-2 block text-sm font-bold">وضعیت درخواست</label>
        <select id="operation-status" name="operation_status" class="min-h-11 rounded-lg border border-slate-300 px-3">
            <option value="">همه وضعیت‌ها</option>
            @foreach(['queued' => 'در صف', 'running' => 'در حال انجام', 'checking' => 'در حال بررسی', 'succeeded' => 'انجام شد', 'failed' => 'ناموفق'] as $value => $label)
            <option value="{{ $value }}" @selected(request('operation_status') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="min-h-11 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white">اعمال فیلتر</button>
    </form>
    <ol class="mt-4 divide-y divide-slate-100">
        @forelse ($operations as $operation)
        <li class="py-4 text-sm">
            <div class="flex flex-wrap justify-between gap-3"><span class="font-bold">{{ $operation->label() }} · {{ $operation->customerStatus() }}</span><time dir="ltr" datetime="{{ $operation->created_at->toIso8601String() }}">{{ $operation->created_at->format('Y/m/d H:i') }}</time></div>
            @if($operation->progressLabel())<p class="mt-2">{{ $operation->progressLabel() }}</p>@endif
            @if($operation->status === 'failed')<p class="mt-2 text-red-700">درخواست کامل نشد. وضعیت سرور را پیش از تلاش مجدد بررسی کنید.</p>@endif
        </li>
        @empty
        <li class="py-4 text-sm text-slate-600">هنوز درخواستی ثبت نشده است.</li>
        @endforelse
    </ol>
    @include('admin.virtual-machines.history-pagination', ['paginator' => $operations, 'label' => 'درخواست‌ها'])
</section>
@if($configurationHistory)
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="text-xl font-bold">تاریخچه تنظیمات و قیمت</h2>
    <ol class="mt-4 divide-y divide-slate-100">
        @forelse($configurationHistory as $change)
        <li class="py-4 text-sm"><p class="font-bold">{{ match ($change->kind) { 'pricing' => 'تغییر قیمت', 'sync' => 'پذیرش سخت‌افزار', 'hardware_requested' => 'درخواست تغییر سخت‌افزار', default => 'تغییر سخت‌افزار' } }} · {{ $change->actor?->name ?? 'مدیر' }}</p><p class="mt-2 break-words">{{ $change->reason }}</p><time datetime="{{ $change->effective_at->toIso8601String() }}">{{ $change->effective_at->format('Y/m/d H:i') }}</time></li>
        @empty
        <li class="py-4 text-sm text-slate-600">تغییری ثبت نشده است.</li>
        @endforelse
    </ol>
    @include('admin.virtual-machines.history-pagination', ['paginator' => $configurationHistory, 'label' => 'تغییرات تنظیمات'])
</section>
@endif
