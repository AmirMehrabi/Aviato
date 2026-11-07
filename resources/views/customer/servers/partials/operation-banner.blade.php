@if ($pendingOperation && ($tab ?? '') !== 'activity')
    <div class="mx-auto my-4 max-w-7xl px-4 md:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900" role="status" aria-live="polite">
            <div>
                <p class="font-bold">{{ $pendingOperation->label() }} · {{ $pendingOperation->customerStatus() }}</p>
                <p class="mt-1 text-xs leading-6">{{ $pendingOperation->progressLabel() ?: 'نتیجه به‌صورت خودکار بررسی می‌شود؛ بستن این صفحه درخواست را لغو نمی‌کند.' }}</p>
            </div>
            <a href="{{ route('customer.servers.tab', [$server, 'tab' => 'activity'], false) }}" class="shrink-0 font-bold underline underline-offset-4">مشاهده در فعالیت‌ها</a>
        </div>
    </div>
@elseif ($powerReady && $server->isRunning() && $latestPowerOperation?->status === 'failed' && in_array($latestPowerOperation->action, ['shutdown', 'restart'], true))
    <div class="mx-auto my-4 max-w-7xl px-4 md:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <p>درخواست {{ $latestPowerOperation->label() }} انجام نشد. اگر سرور پاسخ نمی‌دهد، می‌توانید آن را اجباری خاموش کنید.</p>
            <button type="button" x-data @click="$dispatch('vm-force-stop', @js($server->uuid))" class="font-bold text-red-700 underline underline-offset-4">بررسی خاموش کردن اجباری</button>
        </div>
    </div>
@endif
