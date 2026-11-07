<div class="flex flex-wrap items-start gap-3" x-data="{ menuOpen: false }"
    @vm-force-stop.window="if ($event.detail === @js($server->uuid)) $refs.forceStopDialog.showModal()">
    <div>
        <form method="POST" action="{{ $server->isRunning() ? route('customer.servers.stop', $server, false) : route('customer.servers.start', $server, false) }}"
            x-data="{ confirming: false, submitting: false }" @submit.prevent="if (confirming) { submitting = true; $el.submit(); } else confirming = true">
            @csrf
            @if ($server->isRunning())<input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">@endif
            <button type="submit" :disabled="submitting" class="rounded-xl px-5 py-3 text-sm font-black transition disabled:opacity-50 {{ $server->isRunning() ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-[#0069FF] text-white hover:bg-[#0050D0]' }}">
                <span x-text="confirming ? 'برای تأیید دوباره بزنید' : @js($server->isRunning() ? 'خاموش کردن' : 'روشن کردن')">{{ $server->isRunning() ? 'خاموش کردن' : 'روشن کردن' }}</span>
            </button>
        </form>
        @if ($server->isRunning())<p class="mt-2 max-w-48 text-[11px] leading-5 text-slate-500">سیستم‌عامل به‌صورت عادی خاموش می‌شود.</p>@endif
    </div>
    @if ($server->isRunning())
        <div>
            <form method="POST" action="{{ route('customer.servers.restart', $server, false) }}"
                x-data="{ confirming: false, submitting: false }" @submit.prevent="if (confirming) { submitting = true; $el.submit(); } else confirming = true">
                @csrf
                <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
                <button type="submit" :disabled="submitting" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">
                    <span x-text="confirming ? 'برای تأیید دوباره بزنید' : 'راه‌اندازی مجدد'">راه‌اندازی مجدد</span>
                </button>
            </form>
            <p class="mt-2 max-w-48 text-[11px] leading-5 text-slate-500">درخواست راه‌اندازی مجدد از سیستم‌عامل.</p>
        </div>
        <div class="relative" @click.outside="menuOpen = false" @keydown.escape.stop="menuOpen = false">
            <button type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="vm-power-more" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50">عملیات بیشتر <span aria-hidden="true">⌄</span></button>
            <div id="vm-power-more" x-show="menuOpen" x-cloak class="absolute end-0 top-full z-30 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                <button type="button" @click="menuOpen = false; $refs.forceStopDialog.showModal()" class="w-full rounded-lg p-3 text-start hover:bg-red-50">
                    <span class="block text-sm font-black text-red-700">خاموش کردن اجباری</span>
                    <span class="mt-1 block text-xs leading-6 text-slate-500">برای زمانی که سرور به خاموش کردن عادی پاسخ نمی‌دهد.</span>
                </button>
            </div>
        </div>
        <dialog x-ref="forceStopDialog" aria-labelledby="vm-force-stop-title" aria-describedby="vm-force-stop-description"
            @click="if ($event.target === $el) $el.close()" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border-0 bg-white p-6 text-start text-slate-900 shadow-xl backdrop:bg-slate-950/50">
            <h2 id="vm-force-stop-title" class="text-lg font-black">خاموش کردن اجباری سرور؟</h2>
            <p class="mt-2 text-sm font-bold" dir="auto">{{ $server->display_name }}</p>
            <p id="vm-force-stop-description" class="mt-3 text-sm leading-7 text-slate-600">برق سرور بدون انتظار برای خاموش شدن سیستم‌عامل قطع می‌شود. اطلاعات ذخیره‌نشده ممکن است از بین برود. فقط زمانی استفاده کنید که خاموش کردن عادی پاسخ نمی‌دهد.</p>
            <form method="POST" action="{{ route('customer.servers.force-stop', $server, false) }}" class="mt-6 flex flex-wrap justify-end gap-3"
                x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
                <input type="hidden" name="confirm_force_stop" value="1">
                <button type="button" autofocus @click="$refs.forceStopDialog.close()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold">انصراف</button>
                <button type="submit" :disabled="submitting" class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 disabled:opacity-50">خاموش کردن اجباری</button>
            </form>
        </dialog>
    @endif
</div>
