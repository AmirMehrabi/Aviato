@php
    $machine = $adminConsole ? $vm : $server;
    $powerStatus = ['running' => 'روشن', 'stopped' => 'خاموش', 'suspended' => 'تعلیق', 'deleting' => 'در حال حذف', 'deleted' => 'حذف شده'][$machine->status] ?? 'نامشخص';
    $detailsUrl = $adminConsole ? route('admin.virtual-machines.show', $machine) : route('customer.servers.show', $machine, false);
@endphp
<section x-data="customerVmConsole({ sessionUrl: @js($consoleSessionUrl), csrf: @js(csrf_token()), name: @js($machine->display_name), machineId: @js($machine->id), supportUrl: @js($adminConsole ? null : route('customer.tickets.create', [], false)) })"
    x-ref="workspace" @pagehide.window="disconnect()" @blur.window="releaseKeys(); focused = false"
    :class="{ 'vm-console-expanded': expanded }" class="vm-console space-y-4" aria-label="کنسول ماشین مجازی">
    <style>
        .vm-console { color: #0f172a; background: #f8fafc; }
        .vm-console .console-button { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; min-height: 44px; padding: .5rem .85rem; border: 1px solid #cbd5e1; border-radius: .65rem; background: white; color: #334155; font-size: .8rem; font-weight: 700; cursor: pointer; }
        .vm-console .console-button:hover { background: #eff6ff; }
        .vm-console .console-button:disabled { opacity: .45; cursor: not-allowed; }
        .vm-console .console-button[aria-pressed="true"] { background: #dbeafe; border-color: #60a5fa; color: #1d4ed8; }
        .vm-console :is(button,a,textarea,select,input,summary):focus-visible { outline: 2px solid #0069ff; outline-offset: 3px; }
        .vm-console .console-screen { height: max(18rem, calc(100dvh - 24rem)); }
        .vm-console-expanded, .vm-console:fullscreen { position: fixed; inset: 0; z-index: 100; width: 100%; height: 100dvh; overflow: auto; padding: 1rem; }
        .vm-console-expanded .console-screen, .vm-console:fullscreen .console-screen { height: max(18rem, calc(100dvh - 16rem)); }
        .vm-console dialog::backdrop { background: rgb(2 6 23 / .6); }
        @media (max-width: 640px) { .vm-console .console-screen { height: max(18rem, calc(100dvh - 18rem)); } }
    </style>
    <header class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold text-blue-600">{{ $adminConsole ? 'کنسول مدیریتی ماشین مجازی' : 'کنسول ماشین مجازی' }}</p>
            <h1 class="mt-1 break-words text-2xl font-black text-slate-950" dir="auto">{{ $machine->display_name }}</h1>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
                <span dir="ltr">{{ $machine->name }}</span>
                <span dir="ltr">{{ $machine->ip_address ?: 'بدون IP' }}</span>
                <span>وضعیت ماشین: {{ $powerStatus }}</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $detailsUrl }}" class="console-button">جزئیات سرور</a>
            <button type="button" @click="toggleExpanded()" :aria-pressed="expanded" class="console-button" x-text="expanded ? 'خروج از حالت تمرکز' : 'حالت تمرکز'">حالت تمرکز</button>
            <button type="button" @click="toggleFullscreen()" :aria-pressed="fullscreen" class="console-button" x-text="fullscreen ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'">تمام‌صفحه</button>
        </div>
    </header>
    @if (session('error') || session('status'))
        <p role="status" class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm">{{ session('error') ?: session('status') }}</p>
    @endif
    @if (! $machine->isRunning())
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-7 text-amber-900">ماشین در وضعیت روشن نیست؛ نمایشگر ممکن است تصویر نداشته باشد. برای روشن کردن یا بررسی وضعیت فعلی از جزئیات سرور استفاده کنید.</p>
    @endif
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-3">
            <div class="flex items-center gap-2 text-sm font-bold" role="status" aria-live="polite">
                <span class="size-2 rounded-full" :class="connected ? 'bg-emerald-500' : loading ? 'animate-pulse bg-amber-500' : 'bg-slate-400'" aria-hidden="true"></span>
                <span x-text="statusText"></span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="reconnect()" :disabled="loading" class="console-button">اتصال مجدد</button>
                <button type="button" @click="disconnect()" :disabled="!connected && !loading && !retryIn" class="console-button">قطع اتصال</button>
                <label class="console-button"><span>نمایش</span><select x-model="display" @change="applyDisplay()" class="max-w-32 bg-transparent text-xs" aria-label="اندازه نمایشگر"><option value="fit">متناسب با پنجره</option><option value="actual">اندازه واقعی</option></select></label>
                <label class="console-button"><input type="checkbox" x-model="viewOnly" @change="setViewOnly()"> فقط مشاهده</label>
                <button x-ref="keyboardButton" type="button" @click="panel = panel === 'keyboard' ? '' : 'keyboard'" :aria-expanded="panel === 'keyboard'" aria-controls="console-keyboard" class="console-button">صفحه‌کلید</button>
                <button type="button" @click="panel = panel === 'clipboard' ? '' : 'clipboard'" :aria-expanded="panel === 'clipboard'" aria-controls="console-clipboard" class="console-button">کلیپ‌بورد</button>
                @if (count($powerActions))
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="console-power-menu" class="console-button">برق سرور ⌄</button>
                        <div id="console-power-menu" x-show="open" x-cloak class="absolute end-0 top-full z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                            @foreach ($powerActions as $action)
                                <button type="button" @click="open = false; confirmPower(@js($action))" class="console-button my-1 w-full">{{ $action['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div id="console-keyboard" x-show="panel === 'keyboard'" x-cloak class="space-y-3 border-b border-slate-200 bg-slate-50 p-3">
            <div class="flex flex-wrap gap-2" aria-label="کلیدهای ویژه" dir="ltr">
                <template x-for="name in ['Control', 'Alt', 'Shift']" :key="name"><button type="button" @click="toggleModifier(name)" :aria-pressed="held[name]" :disabled="!connected || viewOnly" class="console-button" x-text="name"></button></template>
                <template x-for="name in ['Escape', 'Tab', 'Enter', 'Backspace', 'Delete', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'ArrowRight']" :key="name"><button type="button" @click="sendKey(name)" :disabled="!connected || viewOnly" class="console-button" x-text="name"></button></template>
                <button type="button" @click="ctrlAltDel()" :disabled="!connected || viewOnly" class="console-button">Ctrl+Alt+Del</button>
                <button type="button" @click="releaseKeys()" class="console-button">رها کردن کلیدها</button>
                <template x-for="number in functionKeys" :key="number"><button type="button" @click="sendFunction(number)" :disabled="!connected || viewOnly" class="console-button" x-text="'F' + number"></button></template>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="mobileKeyboard()" :disabled="!connected || viewOnly" class="console-button">باز کردن صفحه‌کلید دستگاه</button>
                <input x-ref="mobileInput" type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false" :disabled="!connected || viewOnly" @input="sendMobile($event)" @compositionend="sendMobile($event)" @keydown.backspace.prevent="mobileKey('Backspace')" @keydown.enter.prevent="mobileKey('Enter')" class="min-h-11 w-48 rounded-lg border border-slate-300 px-3 text-sm" aria-label="تایپ مستقیم در ماشین" placeholder="تایپ در ماشین" dir="auto">
                <label class="console-button"><input type="checkbox" x-model="drag" @change="applyDisplay()" :disabled="display === 'fit'"> جابه‌جایی تصویر با لمس</label>
            </div>
            <p class="text-xs leading-6 text-slate-500">کلیدهای Ctrl، Alt و Shift تا رها کردن فعال می‌مانند. برای جابه‌جایی تصویر، اندازه واقعی را انتخاب کنید. تایپ این کادر مستقیماً به ماشین ارسال می‌شود.</p>
        </div>
        <div id="console-clipboard" x-show="panel === 'clipboard'" x-cloak class="grid gap-4 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
            <div>
                <label for="console-paste" class="text-sm font-bold">ارسال متن به کلیپ‌بورد ماشین</label>
                <textarea id="console-paste" x-model="clipboard" :disabled="!connected || viewOnly" rows="3" maxlength="65536" class="mt-2 block w-full rounded-lg border border-slate-300 p-3 text-sm" dir="auto"></textarea>
                <button type="button" @click="sendClipboard()" :disabled="!connected || viewOnly || !clipboard" class="console-button mt-2">ارسال متن</button>
                <p class="mt-2 text-xs leading-6 text-slate-500">نیازمند پشتیبانی کلیپ‌بورد در سیستم‌عامل مهمان است؛ در صفحه ورود یا کنسول متنی ممکن است کار نکند. ارسال به معنای اجرای دستور نیست. برای متن غیرلاتین به پشتیبانی کلیپ‌بورد پیشرفته نیاز است.</p>
            </div>
            <div>
                <label for="console-received" class="text-sm font-bold">متن دریافتی از ماشین</label>
                <textarea id="console-received" :value="receivedClipboard" readonly rows="3" class="mt-2 block w-full rounded-lg border border-slate-300 p-3 text-sm" dir="auto" placeholder="هنوز متنی دریافت نشده است؛ ممکن است مهمان پشتیبانی نکند."></textarea>
                <button type="button" @click="copyReceived()" :disabled="!receivedClipboard" class="console-button mt-2">کپی متن دریافتی</button>
            </div>
        </div>
        <div class="console-screen relative overflow-auto bg-black" dir="ltr" @pointerdown="focusScreen()">
            <div x-ref="screen" class="absolute inset-0" :class="{ 'pointer-events-none': !connected }"></div>
            <div x-show="!connected" class="absolute inset-0 z-10 grid place-items-center overflow-auto bg-slate-950/95 p-5 text-center text-white">
                <div class="max-w-lg space-y-3" dir="rtl">
                    <div x-show="loading" class="mx-auto size-8 animate-spin rounded-full border-2 border-white/20 border-t-white" aria-hidden="true"></div>
                    <p class="text-base font-bold" x-text="loading ? stage : error || 'اتصال کنسول قطع است'"></p>
                    <p class="text-xs leading-6 text-slate-300">قطع اتصال کنسول، ماشین مجازی را خاموش نمی‌کند.</p>
                    <p x-show="retryIn" class="text-sm" aria-live="polite">تلاش مجدد تا <span x-text="retryIn"></span> ثانیه دیگر (<span x-text="retryCount"></span> از ۳)</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button x-show="!loading" type="button" @click="reconnect()" class="console-button">اتصال مجدد</button>
                        <button x-show="retryIn" type="button" @click="cancelRetry()" class="console-button">توقف تلاش خودکار</button>
                        <button x-show="errorCode === 'portal_session'" type="button" @click="window.location.reload()" class="console-button">تازه کردن صفحه</button>
                    </div>
                    <p x-show="reference" class="break-all text-xs text-slate-400">کد پیگیری: <span x-text="reference" dir="ltr"></span></p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2 text-xs leading-6 text-slate-500">
            <p x-text="viewOnly ? 'حالت فقط مشاهده فعال است' : focused ? 'صفحه‌کلید در اختیار ماشین است' : 'برای تایپ، داخل نمایشگر کلیک کنید'"></p>
            <p>بازگشت به کنترل‌های صفحه: <bdi dir="ltr">Ctrl + Alt + Shift + Esc</bdi></p>
        </div>
    </div>
    <p x-show="notice" x-cloak x-text="notice" role="status" aria-live="polite" class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm leading-7 text-blue-900"></p>
    <details class="rounded-xl border border-slate-200 bg-white p-4">
        <summary class="cursor-pointer text-sm font-bold">راهنما و عیب‌یابی اتصال</summary>
        <div class="mt-3 space-y-3 text-sm leading-7 text-slate-600">
            <p>اگر تصویر سیاه است، ابتدا وضعیت سرور را در جزئیات بررسی کنید. برای ارسال کلیدهای رزروشده مرورگر از صفحه‌کلید ابزار استفاده کنید. کلید Escape ممکن است حالت تمام‌صفحه مرورگر را ببندد.</p>
            <p>در موبایل، از صفحه‌کلید دستگاه و کلیدهای کمکی استفاده کنید. حالت فقط مشاهده، ورودی صفحه‌کلید و ماوس را غیرفعال می‌کند.</p>
            <pre class="overflow-auto rounded-lg bg-slate-50 p-3 text-xs" dir="ltr" x-text="diagnostics()"></pre>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="copyDiagnostics()" class="console-button">کپی گزارش اتصال</button>
                @unless ($adminConsole)
                    <a href="{{ route('customer.tickets.create', [], false) }}" :href="supportUrl()" class="console-button">تماس با پشتیبانی</a>
                    <span class="text-xs">کد پیگیری به فرم پشتیبانی اضافه می‌شود. برای جزئیات بیشتر گزارش اتصال را کپی کنید.</span>
                @endunless
            </div>
        </div>
    </details>
    @if ($adminConsole)
        <details class="rounded-xl border border-slate-200 bg-white p-4">
            <summary class="cursor-pointer text-sm font-bold">اطلاعات مدیریتی و تاریخچه دسترسی</summary>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">مالک</dt><dd class="font-bold">{{ $vm->customer?->name ?: $vm->project?->owner?->name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">نود</dt><dd dir="ltr">{{ $vm->node ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">VMID</dt><dd dir="ltr">{{ $vm->vmid ?: '—' }}</dd></div>
            </dl>
            <p class="mt-4 text-xs leading-6 text-slate-500">آخرین درخواست‌های ایجاد نشست؛ این تاریخچه به معنی اتصال موفق نمایشگر یا ثبت محتوای صفحه نیست. برای مشاهده درخواست‌های جدید صفحه را تازه کنید.</p>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @forelse ($consoleHistory as $entry)
                    <li class="flex flex-wrap justify-between gap-2 py-3">
                        <span>{{ data_get($entry->metadata, 'actor_name', 'کاربر') }} · {{ data_get($entry->metadata, 'portal') === 'admin' ? 'مدیر' : 'مشتری' }} · {{ $entry->outcome === 'success' ? 'نشست صادر شد' : 'ایجاد نشست ناموفق' }}</span>
                        <time datetime="{{ $entry->created_at->toISOString() }}">{{ \App\Support\Jalali::format($entry->created_at, 'Y/m/d H:i') }}</time>
                        <span class="w-full break-all text-xs text-slate-500" dir="ltr">{{ data_get($entry->metadata, 'reference') }}</span>
                    </li>
                @empty
                    <li class="py-3 text-slate-500">هنوز درخواستی ثبت نشده است.</li>
                @endforelse
            </ul>
        </details>
    @endif
    <dialog x-ref="powerDialog" aria-labelledby="console-power-title" @click="if ($event.target === $el) $el.close()" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border-0 bg-white p-6 text-start text-slate-900 shadow-xl">
        <h2 id="console-power-title" class="text-lg font-black" x-text="pendingPower?.label"></h2>
        <p class="mt-3 break-words font-bold" dir="auto">{{ $machine->display_name }}</p>
        <p class="mt-3 text-sm leading-7 text-slate-600" x-text="pendingPower?.warning"></p>
        <form method="POST" :action="pendingPower?.url" x-data="{ submitting: false }" @submit="submitting = true" class="mt-5 flex flex-wrap justify-end gap-2">
            @csrf
            <input type="hidden" name="power_generation" value="{{ (int) data_get($machine->desired_state, 'power_generation', 0) }}">
            <input type="hidden" name="confirm_force_stop" :disabled="pendingPower?.key !== 'force-stop'" value="1">
            <button type="button" @click="$refs.powerDialog.close()" class="console-button" autofocus>انصراف</button>
            <button type="submit" :disabled="submitting || !pendingPower" class="console-button" x-text="submitting ? 'در حال ارسال…' : 'تأیید عملیات'"></button>
        </form>
    </dialog>
</section>
