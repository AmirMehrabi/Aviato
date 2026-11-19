<div class="relative {{ $showRunningActions ? 'max-sm:col-span-2' : '' }}"
    @click.outside="menuOpen = false"
    @keydown.escape="if (menuOpen) { $event.stopPropagation(); menuOpen = false; $refs.more.focus(); }"
    @if ($showRunningActions)
        @vm-force-stop.window="if ($event.detail === @js($server->uuid)) { menuOpen = false; $refs.forceStopDialog.showModal(); }"
    @endif
>
    <button type="button" x-ref="more" @click="menuOpen = !menuOpen" aria-expanded="false" :aria-expanded="menuOpen" aria-controls="vm-more-actions" class="{{ $actionClass }}">
        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h.01M12 12h.01M19 12h.01"/></svg>
        عملیات بیشتر
    </button>
    <div id="vm-more-actions" x-show="menuOpen" x-cloak class="absolute end-0 top-full z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
        <button type="button" @click="menuOpen = false; start()" :disabled="editing" class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm font-black text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#0069FF] disabled:opacity-50">
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 3 5 5M4 20l4-1L20 7a3.5 3.5 0 0 0-5-5L3 14l-1 8 2-2Z"/></svg>
            ویرایش نام نمایشی
        </button>
        @if ($showRunningActions)
            <button type="button" @click="menuOpen = false; $refs.forceStopDialog.showModal()" class="w-full rounded-lg p-3 text-start hover:bg-red-50">
                <span class="block text-sm font-black text-red-700">خاموش کردن اجباری</span>
                <span class="mt-1 block text-xs leading-6 text-slate-500">برای زمانی که سرور به خاموش کردن عادی پاسخ نمی‌دهد.</span>
            </button>
        @endif
    </div>
    @if ($showRunningActions)
        <dialog x-ref="forceStopDialog" aria-labelledby="vm-force-stop-title" aria-describedby="vm-force-stop-description"
            @close="$refs.more.focus()" @click="if ($event.target === $el) $el.close()" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border-0 bg-white p-4 text-start text-slate-900 shadow-xl backdrop:bg-slate-950/50 sm:p-6">
            <h2 id="vm-force-stop-title" class="text-lg font-black">خاموش کردن اجباری سرور؟</h2>
            <p class="mt-2 wrap-anywhere text-sm font-bold"><bdi>{{ $server->display_name }}</bdi></p>
            <p id="vm-force-stop-description" class="mt-3 text-sm leading-7 text-slate-600">برق سرور بدون انتظار برای خاموش شدن سیستم‌عامل قطع می‌شود. اطلاعات ذخیره‌نشده ممکن است از بین برود. فقط زمانی استفاده کنید که خاموش کردن عادی پاسخ نمی‌دهد.</p>
            <form method="POST" action="{{ route('customer.servers.force-stop', $server, false) }}" class="mt-5 grid grid-cols-[auto_1fr] gap-2 sm:flex sm:justify-end"
                x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
                <input type="hidden" name="confirm_force_stop" value="1">
                <button type="button" autofocus @click="$refs.forceStopDialog.close()" class="{{ $actionClass }}">انصراف</button>
                <button type="submit" :disabled="submitting" class="min-h-11 rounded-xl bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800 disabled:opacity-50">خاموش کردن اجباری</button>
            </form>
        </dialog>
    @endif
</div>
