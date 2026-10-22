@php
    $published = $zone->verified_at && $zone->status === 'ready' && $zone->synced_revision === $zone->revision && ! $zone->last_error;
    $connected = $zone->delegation_status === 'active';
    $expired = ! $zone->verified_at && $zone->verification_expires_at?->isPast();
@endphp
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
        <div><h2 class="font-black">{{ $connected && $published ? 'دامنه به آویاتو متصل است' : 'راه‌اندازی دامنه، قدم‌به‌قدم' }}</h2><p class="mt-2 text-xs leading-6 text-slate-500">{{ $connected && $published ? 'مالکیت تأیید شده و آخرین رکوردها در هر دو نیم‌سرور منتشر شده‌اند.' : 'ابتدا مالکیت را تأیید کنید؛ سپس رکوردها را آماده کنید و نیم‌سرورها را تغییر دهید.' }}</p></div>
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $connected ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">{{ $connected ? 'نیم‌سرورها متصل‌اند' : ($zone->checked_at ? 'نیم‌سرورها هنوز متصل نیستند' : 'اتصال هنوز بررسی نشده') }}</span>
    </div>
    <ol class="grid divide-y divide-slate-100 md:grid-cols-3 md:divide-y-0">
        @foreach([
            ['تأیید مالکیت', (bool) $zone->verified_at, $zone->verified_at ? 'مالکیت دامنه تأیید شده' : 'یک رکورد TXT در DNS فعلی بسازید'],
            ['آماده‌سازی رکوردها', (bool) $published, $published ? 'آخرین تغییرات در هر دو سرور منتشر شده' : ($zone->verified_at ? 'رکوردها را وارد کنید و منتظر انتشار بمانید' : 'رکوردها را می‌توانید از همین حالا وارد کنید')],
            ['اتصال نیم‌سرورها', $connected, $connected ? 'هر دو نام در رجیسترار تنظیم شده‌اند' : 'پس از آماده‌سازی، در رجیسترار تغییر دهید'],
        ] as [$label, $done, $description])
            <li class="flex gap-3 p-5"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $done ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-[#0069FF]' }}">{{ $done ? '✓' : $loop->iteration }}</span><div><p class="text-sm font-bold">{{ $label }}</p><p class="mt-2 text-xs leading-6 text-slate-500">{{ $description }}</p></div></li>
        @endforeach
    </ol>
    @unless($zone->verified_at)
        <div class="border-t border-blue-100 bg-blue-50/40 p-5 sm:p-6">
            <h3 class="font-bold">{{ $expired ? 'مهلت تأیید این دامنه تمام شده است' : 'این رکورد را در سرویس DNS فعلی دامنه اضافه کنید' }}</h3>
            @if($expired)
                <p class="mt-2 text-sm leading-7 text-amber-900">برای دریافت کد تازه، دامنه تأییدنشده را از پایین صفحه حذف و دوباره اضافه کنید.</p>
            @else
                <p class="mt-2 text-sm leading-7 text-slate-600">اگر از Cloudflare استفاده می‌کنید، به بخش DNS → Records بروید و Add record را بزنید. نوع را TXT و TTL را Auto بگذارید. فعلاً نیم‌سرورها را تغییر ندهید.</p>
                <div class="mt-4 overflow-hidden rounded-xl border border-blue-100 bg-white">
                    @foreach(['نوع رکورد' => 'TXT', 'نام (Name)' => '_aviato-verification', 'مقدار (Content)' => $zone->verification_token] as $label => $value)
                        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0"><span class="w-full text-xs font-bold text-slate-500 sm:w-28">{{ $label }}</span><code dir="ltr" class="min-w-0 flex-1 select-all break-all text-left text-sm">{{ $value }}</code><button type="button" @click="copy({{ \Illuminate\Support\Js::from($value) }})" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-[#0069FF]" aria-label="کپی {{ $label }}">کپی</button></div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs leading-6 text-slate-500">نام کامل رکورد: <bdi class="select-all font-mono">_aviato-verification.{{ $zone->name }}</bdi>. در سرویس‌هایی که دامنه را خودکار اضافه می‌کنند فقط نام کوتاه بالا را وارد کنید.</p>
                @if($editable && ! $admin)
                    <form method="POST" action="{{ route($prefix.'verify', $zone, false) }}" class="mt-4 flex flex-wrap items-center gap-3" x-data="{ checking: false }" @submit="checking = true">@csrf<button :disabled="checking" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white disabled:opacity-50" x-text="checking ? 'در حال بررسی رکورد…' : 'رکورد را اضافه کردم؛ بررسی مالکیت'">رکورد را اضافه کردم؛ بررسی مالکیت</button><span class="text-xs text-slate-500">اعتبار کد تا {{ \App\Support\Jalali::format($zone->verification_expires_at) }}</span></form>
                @endif
                <details class="mt-4 text-xs leading-7 text-slate-600"><summary class="cursor-pointer font-bold">رکورد پیدا نشد؟</summary><p class="mt-2">نام و مقدار را بدون فاصله اضافی کپی کنید. رکورد باید در سرویس فعلی DNS ذخیره شده باشد. انتشار تغییر ممکن است چند دقیقه طول بکشد؛ کمی بعد دوباره بررسی کنید.</p></details>
            @endif
        </div>
    @endunless
    <details class="border-t border-slate-100 p-5 sm:p-6" @if($zone->verified_at && ! $connected) open @endif>
        <summary class="cursor-pointer text-sm font-bold">{{ $connected ? 'مشاهده نیم‌سرورها و بررسی مجدد اتصال' : 'نیم‌سرورهای آویاتو و راهنمای اتصال' }}</summary>
        <div class="mt-4 space-y-4">
            @unless($connected)
                <p class="text-sm leading-7 text-slate-600">ابتدا همه رکوردهای وب‌سایت و ایمیل (MX، SPF و DKIM) را در جدول پایین وارد یا از فایل سرویس قبلی وارد کنید. پس از انتشار، در پنل <strong>ثبت‌کننده دامنه (رجیسترار)</strong>، نیم‌سرورهای قبلی را با این دو نام جایگزین کنید. برای دامنه‌های .ir این کار در ایرنیک انجام می‌شود؛ افزودن رکورد NS در Cloudflare کافی نیست.</p>
                @unless($published)<p class="rounded-xl bg-amber-50 p-3 text-xs leading-7 text-amber-900">هنوز آماده تغییر نیم‌سرورها نیستید: {{ ! $zone->verified_at ? 'ابتدا مالکیت را تأیید کنید.' : 'منتظر انتشار آخرین تغییرات در هر دو سرور بمانید.' }}</p>@endunless
            @endunless
            <div class="grid gap-3 sm:grid-cols-2">@foreach(config('cloud-dns.nameservers') as $ns)<div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"><code dir="ltr" class="select-all text-sm">{{ $ns }}</code><button type="button" @click="copy({{ \Illuminate\Support\Js::from($ns) }})" class="text-xs font-bold text-[#0069FF]" aria-label="کپی {{ $ns }}">کپی</button></div>@endforeach</div>
            <div class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-bold text-slate-500">نیم‌سرورهای مشاهده‌شده در آخرین بررسی</p><p class="mt-2 break-all font-mono text-sm" dir="ltr">{{ $zone->observed_nameservers ? implode(' · ', $zone->observed_nameservers) : '—' }}</p><p class="mt-2 text-xs text-slate-500">{{ $zone->checked_at?->diffForHumans() ?? 'هنوز بررسی نشده' }}</p></div>
            @if($editable && ! $admin)<form method="POST" action="{{ route($prefix.'check', $zone, false) }}" x-data="{ checking: false }" @submit="checking = true">@csrf<button :disabled="checking" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold disabled:opacity-50" x-text="checking ? 'در حال بررسی اتصال…' : 'بررسی دوباره نیم‌سرورها'">بررسی دوباره نیم‌سرورها</button></form>@endif
            <p class="text-xs leading-7 text-slate-500">تغییر در رجیسترار ممکن است با تأخیر دیده شود. اگر DNSSEC فعال دارید، پیش از انتقال، DS سرویس قبلی را با رعایت زمان انقضای کش از رجیسترار حذف کنید.</p>
        </div>
    </details>
</section>
