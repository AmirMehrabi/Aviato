<div x-data="{ submitting: false }">
    @if ($showPowerRadios)
        <fieldset class="min-w-0" @change="$refs.currentPower.checked = true; $refs.powerDialog.showModal()">
            <legend class="sr-only">وضعیت برق سرور</legend>
            <div class="inline-flex gap-0.5 rounded-xl border border-slate-200 bg-slate-50 p-0.5">
                @foreach (['on' => 'روشن', 'off' => 'خاموش'] as $value => $label)
                    @php($current = ($value === 'on') === $server->isRunning())
                    <label class="cursor-pointer">
                        <input type="radio" name="vm-power-state" value="{{ $value }}" class="peer sr-only" @checked($current) @if ($current) x-ref="currentPower" @endif disabled :disabled="submitting">
                        <span class="flex min-h-11 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-bold text-slate-600 transition hover:bg-white peer-checked:shadow-sm peer-focus-visible:outline-2 peer-focus-visible:outline-[#0069FF] peer-disabled:cursor-default {{ $value === 'on' ? 'peer-checked:bg-emerald-50 peer-checked:text-emerald-700' : 'peer-checked:bg-white peer-checked:text-slate-900' }}">
                            <span class="size-1.5 rounded-full {{ $value === 'on' ? 'bg-emerald-500' : 'bg-slate-400' }}" aria-hidden="true"></span>
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @else
        <button type="button" class="{{ $actionClass }}" @click="$refs.powerDialog.showModal()">روشن کردن</button>
    @endif

    <dialog x-ref="powerDialog" aria-labelledby="vm-power-title" aria-describedby="vm-power-description"
        @click="if ($event.target === $el && !submitting) $el.close()" @cancel="if (submitting) $event.preventDefault()"
        class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border-0 bg-white p-4 text-start text-slate-900 shadow-xl backdrop:bg-slate-950/50 sm:p-6">
        <h2 id="vm-power-title" class="text-lg font-black">{{ $server->isRunning() ? 'سرور خاموش شود؟' : 'سرور روشن شود؟' }}</h2>
        <p class="mt-2 wrap-anywhere text-sm font-bold"><bdi>{{ $server->display_name }}</bdi></p>
        <p id="vm-power-description" class="mt-3 text-sm leading-7 text-slate-600">
            {{ $server->isRunning() ? 'درخواست خاموش شدن عادی به سیستم‌عامل ارسال می‌شود و دسترسی به سرویس‌های سرور قطع خواهد شد.' : 'درخواست روشن کردن سرور ثبت می‌شود. می‌توانید پیشرفت آن را در این صفحه دنبال کنید.' }}
        </p>
        <form method="POST" action="{{ $server->isRunning() ? route('customer.servers.stop', $server, false) : route('customer.servers.start', $server, false) }}"
            class="mt-5 grid grid-cols-2 gap-2 sm:flex sm:justify-end" @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">
            @csrf
            @if ($server->isRunning())
                <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
            @endif
            <button type="button" autofocus @click="$refs.powerDialog.close()" :disabled="submitting" class="{{ $actionClass }}">انصراف</button>
            <button type="submit" :disabled="submitting" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0069FF] px-3 py-2 text-center text-sm font-bold text-white transition hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] disabled:opacity-50">
                {{ $server->isRunning() ? 'خاموش کردن' : 'روشن کردن' }}
            </button>
        </form>
    </dialog>
</div>
