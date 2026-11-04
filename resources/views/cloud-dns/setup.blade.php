<section id="domain-nameservers" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="space-y-4 p-5 sm:p-6">
        <div><h2 class="font-black">نیم‌سرورهای دامنه</h2><p class="mt-2 text-sm font-bold" x-text="connection.label" aria-live="polite">{{ $zone->connectionState()['label'] }}</p></div>
        <p class="text-xs leading-7 text-slate-600">برای اتصال کل دامنه، این دو نام را در پنل ثبت‌کننده دامنه (رجیسترار) جایگزین کنید. این NSهای ریشه به‌صورت خودکار مدیریت می‌شوند و قابل ویرایش نیستند. رکورد NS در جدول پایین فقط برای واگذاری زیردامنه است.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach(config('cloud-dns.nameservers') as $ns)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"><code dir="ltr" class="select-all text-sm">{{ $ns }}</code><button type="button" @click="copy({{ \Illuminate\Support\Js::from($ns) }})" class="text-xs font-bold text-[#0069FF]" aria-label="کپی {{ $ns }}">کپی</button></div>
            @endforeach
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-bold text-slate-500">نیم‌سرورهای مشاهده‌شده در آخرین بررسی</p>
            <p class="mt-2 break-all font-mono text-sm" dir="ltr" x-text="connection.observed.length ? connection.observed.join(' · ') : '—'"></p>
            <p class="mt-2 text-xs text-slate-500"><span x-text="connection.checked"></span> · <span x-show="!connection.connected">{{ $zone->checked_at ? 'نیم‌سرورها هنوز متصل نیستند' : 'اتصال هنوز بررسی نشده' }}</span></p>
            @if($editable && ! $admin)
                <form method="POST" action="{{ route($prefix.'check', $zone, false) }}" x-data="{ checking: false }" @submit="checking = true" class="mt-3">@csrf<button :disabled="checking" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold disabled:opacity-50" x-text="checking ? 'در حال بررسی اتصال…' : 'بررسی دوباره نیم‌سرورها'">بررسی دوباره نیم‌سرورها</button></form>
            @endif
        </div>
        <div class="text-xs leading-7 text-slate-500" aria-live="polite">
            <p>انتشار روی هر دو سرور: <span x-text="connection.published ? 'انجام شده' : 'در انتظار یا نیازمند بررسی'"></span> · آخرین انتشار: <span x-text="connection.synced"></span></p>
            <button type="button" @click="refreshStatus()" :disabled="refreshing" class="font-bold text-[#0069FF]">به‌روزرسانی وضعیت</button>
<p x-show="recordsStale" class="text-amber-900">رکوردها در صفحه دیگری تغییر کرده‌اند. پیش از مرور انتقال، <button type="button" @click="window.location.reload()" class="font-bold underline">رکوردهای تازه را بارگذاری کنید</button>.</p>
            <p x-show="statusError" x-text="statusError" class="text-amber-900"></p>
        </div>
    </div>
    @unless($zone->verified_at)
        <details open class="border-t border-blue-100 bg-blue-50/30 p-5 sm:p-6">
            <summary class="cursor-pointer text-sm font-bold">۱. تأیید اتصال دامنه</summary>
            <p class="mt-3 text-sm leading-7 text-slate-600">نیم‌سرورهای دامنه را در پنل ثبت‌کننده با نیم‌سرورهای آویاتو که در بالا نمایش داده شده جایگزین کنید. پس از انتشار تغییر، اتصال را بررسی کنید تا دامنه فعال شود.</p>
            @if($editable && ! $admin)
                <form method="POST" action="{{ route($prefix.'verify', $zone, false) }}" class="mt-4" x-data="{ checking: false }" @submit="checking = true">@csrf<button :disabled="checking" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white disabled:opacity-50" x-text="checking ? 'در حال بررسی نیم‌سرورها…' : 'بررسی اتصال و فعال‌سازی'">بررسی اتصال و فعال‌سازی</button></form>
            @endif
        </details>
    @endunless
    <details x-show="!connection.connected" @if($zone->verified_at && $zone->delegation_status !== 'active') open @endif class="border-t border-slate-100 p-5 sm:p-6">
        <summary class="cursor-pointer text-sm font-bold">۲. مرور رکوردها و آمادگی انتقال</summary>
        <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600">
            <p class="text-xs">انتشار موفق به معنی کامل بودن رکوردهای شما نیست. این چک‌لیست فقط تأیید شما در همین صفحه است و اتصال دامنه را تغییر نمی‌دهد.</p>
            <p class="text-xs text-amber-900" x-show="!records.some(r => ['A', 'AAAA', 'CNAME'].includes(r.type))">رکورد اتصال وب‌سایت پیدا نشد؛ اگر وب‌سایت دارید، رکوردهای آن را وارد کنید.</p>
            <p class="text-xs text-amber-900" x-show="!records.some(r => r.type === 'MX')">رکورد MX پیدا نشد؛ اگر ایمیل روی دامنه دارید، MX و رکوردهای SPF و DKIM را بررسی کنید.</p>
            <label class="flex gap-2"><input type="checkbox" x-model="recordsReviewed" class="mt-2 shrink-0">رکوردهای وب‌سایت و ایمیل را با سرویس قبلی مقایسه کردم.</label>
            <label class="flex gap-2"><input type="checkbox" x-model="emptyDomain" class="mt-2 shrink-0">این دامنه هنوز سرویس فعالی ندارد.</label>
            <label class="flex gap-2"><input type="checkbox" x-model="dnssecReviewed" class="mt-2 shrink-0">DNSSEC غیرفعال است یا DS سرویس قبلی را از رجیسترار حذف کرده‌ام و زمان انقضای کش آن گذشته است.</label>
            <p class="text-xs text-amber-900">Aviato در این مرحله وضعیت DS را خودکار تأیید نمی‌کند. اگر از وضعیت DNSSEC مطمئن نیستید، پیش از جابه‌جایی با پشتیبانی هماهنگ کنید.</p>
            <p class="text-sm font-bold text-slate-900" x-text="!connection.published ? 'ابتدا اتصال نیم‌سرورها را تکمیل کنید.' : (migrationReviewed ? 'مرور شما تکمیل است؛ اکنون نیم‌سرورها را در رجیسترار تغییر دهید.' : 'رکوردها منتشر شده‌اند؛ پیش از تغییر نیم‌سرورها چک‌لیست را کامل کنید.')"></p>
        </div>
    </details>
    <details class="border-t border-slate-100 p-5 sm:p-6">
        <summary class="cursor-pointer text-sm font-bold">۳. راهنمای تغییر نیم‌سرورها و پیگیری اتصال</summary>
        <div class="mt-4 space-y-3 text-xs leading-7 text-slate-600">
            <p>ابتدا همه رکوردهای وب‌سایت و ایمیل را در جدول پایین وارد یا از فایل سرویس قبلی وارد کنید. پس از تأیید مالکیت، انتشار و مرور رکوردها، در پنل ثبت‌کننده دامنه، نیم‌سرورهای قبلی را با دو نام بالا جایگزین کنید. برای دامنه‌های .ir این کار در ایرنیک انجام می‌شود؛ افزودن رکورد NS در Cloudflare کافی نیست.</p>
            <p>تنظیمات پروکسی و CNAME flattening در Cloudflare به آویاتو منتقل نمی‌شوند. اتصال مستقیم وب‌سایت و گواهی HTTPS سرور را پیش از جابه‌جایی بررسی کنید.</p>
            <p>وضعیت صفحه خودکار به‌روز می‌شود؛ بررسی پس‌زمینه نیم‌سرورها حدود هر ۳۰ دقیقه انجام می‌شود. برای بررسی فوری دکمه بررسی دوباره را بزنید. تغییر در رجیسترار و انقضای کش ممکن است با تأخیر دیده شود.</p>
        </div>
    </details>
</section>
