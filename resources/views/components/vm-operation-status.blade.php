@php
    $recentOperations = $machine->operations()->latest('id')->limit(5)->get();
    $pendingOperation = $recentOperations->firstWhere('active_vm_id', $machine->id);
@endphp
@if ($recentOperations->isNotEmpty())
<section class="my-5 rounded-2xl border border-slate-200 bg-white p-5" aria-label="درخواست‌های سرور"
    x-data="{ timer: null, init() { if (@js((bool) $pendingOperation)) this.timer = setTimeout(() => window.location.reload(), 15000); }, destroy() { clearTimeout(this.timer); } }">
    <h2 class="font-bold text-slate-900">درخواست‌های سرور</h2>
    @if ($pendingOperation)
        <div class="mt-3 rounded-xl bg-blue-50 p-4 text-sm leading-7 text-blue-900" role="status" aria-live="polite">
            <p class="font-bold">{{ $pendingOperation->label() }} · {{ $pendingOperation->customerStatus() }}</p>
            @if ($pendingOperation->progressLabel())
                <p>{{ $pendingOperation->progressLabel() }}</p>
            @endif
            @if ($pendingOperation->action === 'upgrade')
                @php($changes = $pendingOperation->details['requested_changes'] ?? [])
                <p>منابع درخواستی:
                    @if (isset($changes['cpu_cores'])) {{ $changes['cpu_cores'] }} هسته پردازنده، {{ $changes['ram_gb'] }} گیگابایت حافظه، {{ $changes['disk_gb'] }} گیگابایت فضای ذخیره‌سازی
                    @elseif (isset($changes['size_gb'])) {{ $changes['size_gb'] }} گیگابایت فضای ذخیره‌سازی اضافه
                    @endif
                </p>
            @endif
            <p>از ثبت درخواست {{ $pendingOperation->created_at->diffForHumans(parts: 1, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }} گذشته است. وضعیت به‌صورت خودکار بررسی می‌شود؛ بستن این صفحه درخواست را لغو نمی‌کند.</p>
            @if ($pendingOperation->status === 'checking' || $pendingOperation->created_at->lt(now()->subMinutes(3)))
                <p>انجام درخواست هنوز تأیید نشده است. برای جلوگیری از تداخل، درخواست دیگری ثبت نکنید. اگر این وضعیت ادامه داشت، با پشتیبانی تماس بگیرید.</p>
            @else
                <p>تا پایان این درخواست، تغییرات دیگر در دسترس نیست. پس از روشن شدن سرور، آماده شدن اتصال ممکن است چند دقیقه طول بکشد.</p>
            @endif
        </div>
    @endif
    <ol class="mt-3 divide-y divide-slate-100 text-sm">
        @foreach ($recentOperations as $operation)
            <li class="flex flex-wrap justify-between gap-2 py-3">
                <span>{{ $operation->label() }} · {{ $operation->customerStatus() }}</span>
                <time class="text-slate-500" datetime="{{ $operation->created_at->toIso8601String() }}">{{ $operation->created_at->diffForHumans() }}</time>
                @if ($operation->status === 'failed')
                    <p class="w-full text-slate-600">درخواست کامل نشد. وضعیت سرور را بررسی کنید؛ اگر مشکل ادامه دارد، با پشتیبانی تماس بگیرید.</p>
                @endif
            </li>
        @endforeach
    </ol>
</section>
@endif
