<div class="space-y-5">
    <div>
        <h2 class="text-xl font-black text-slate-950">فعالیت‌های سرور</h2>
        <p class="mt-1 text-sm text-slate-500">درخواست‌ها، پیشرفت و نتیجه عملیات سرور در یک تاریخچه</p>
    </div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <ol class="divide-y divide-slate-100">
            @forelse ($activities as $activity)
                @php($operation = $activity->operation)
                <li class="flex gap-4 p-5" @if($operation) data-operation-id="{{ $operation->id }}" @endif>
                    <span class="mt-1 grid size-9 shrink-0 place-items-center rounded-full {{ $activity->outcome === 'failed' ? 'bg-red-50 text-red-600' : ($activity->outcome === 'succeeded' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v9l6 3m4-3a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-black text-slate-950">{{ $activity->title }}@if($operation) · {{ $operation->customerStatus() }}@endif</h3>
                            <time class="text-xs text-slate-500" datetime="{{ $activity->created_at->toIso8601String() }}">{{ \App\Support\Jalali::format($activity->created_at) }}</time>
                        </div>
                        @if ($activity->detail)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $activity->detail }}</p>@endif
                        @if ($operation?->action === 'upgrade')
                            @php($changes = $operation->details['requested_changes'] ?? [])
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                @if (isset($changes['cpu_cores'])) منابع درخواستی: {{ $changes['cpu_cores'] }} هسته پردازنده، {{ $changes['ram_gb'] }} گیگابایت حافظه، {{ $changes['disk_gb'] }} گیگابایت دیسک
                                @elseif (isset($changes['size_gb'])) دیسک اضافه: {{ $changes['size_gb'] }} گیگابایت
                                @endif
                            </p>
                        @endif
                        @if ($operation?->active_vm_id)
                            <p class="mt-2 text-sm leading-6 text-slate-600">تا مشخص شدن نتیجه، درخواست دیگری ثبت نکنید. بستن این صفحه درخواست را لغو نمی‌کند.</p>
                            @if ($operation->status === 'checking' || $operation->created_at->lt(now()->subMinutes(3)))
                                <p class="mt-1 text-sm leading-6 text-amber-800">انجام درخواست هنوز تأیید نشده است؛ نتیجه به‌صورت خودکار بررسی می‌شود. اگر این وضعیت ادامه داشت، با پشتیبانی تماس بگیرید.</p>
                            @endif
                        @elseif ($operation?->status === 'failed')
                            <p class="mt-2 text-sm leading-6 text-slate-600">درخواست انجام نشد. وضعیت سرور را بررسی کنید.</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="p-8 text-center text-sm text-slate-500">هنوز فعالیتی برای این سرور ثبت نشده است.</li>
            @endforelse
        </ol>
    </section>
    @if ($activities->hasPages())<div>{{ $activities->links() }}</div>@endif
</div>
