<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>کارت هدیه آویاتو</title><link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">@vite(['resources/css/app.css'])</head>
<body class="grid min-h-screen place-items-center bg-[#F5F8FD] p-4 text-slate-950">
<main class="w-full max-w-lg rounded-[32px] border border-blue-100 bg-white p-7 text-center shadow-2xl shadow-blue-100">
    <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو" class="mx-auto h-12 w-auto">
    <p class="mt-8 text-xs font-black text-[#0069FF]">کارت هدیه آویاتو</p><h1 class="mt-2 text-2xl font-black">{{ $campaign->headline ?: $campaign->name }}</h1>
    <p class="mt-3 text-sm font-bold leading-7 text-slate-500">{{ $campaign->message ?: 'برای اعمال هدیه وارد پنل مشتریان شوید و فضای کاری مقصد را بررسی کنید.' }}</p>
    <div class="mt-5 rounded-2xl bg-[#EEF5FF] p-4 text-sm font-black text-[#0050D0]">{{ $campaign->type === 'wallet_credit' ? app(\App\Services\WalletService::class)->format($campaign->credit_amount) . ($campaign->requiresPayment() ? ' هدیه پس از پرداخت موفق' : ' اعتبار مستقیم') : $campaign->percentage.'٪ پاداش افزایش موجودی تا سقف '.app(\App\Services\WalletService::class)->format($campaign->maximum_bonus) }}</div>
    @auth('customer')
        <a id="continue" href="{{ route('customer.wallet.show', ['gift_card' => 1], false) }}" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#0069FF] px-5 font-black text-white">ادامه و استفاده از کارت</a>
    @else
        <div class="mt-6 grid gap-2 sm:grid-cols-2">
            <a id="continue" href="{{ route('customer.gift-cards.continue', [$campaign, 'register'], false) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#0069FF] px-5 font-black text-white">ساخت حساب</a>
            <a href="{{ route('customer.gift-cards.continue', [$campaign, 'login'], false) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-200 px-5 font-black text-slate-700">ورود</a>
        </div>
    @endauth
    <p class="mt-4 text-xs font-bold text-slate-400">انقضا: {{ \Morilog\Jalali\Jalalian::fromCarbon($campaign->expires_at)->format('Y/m/d H:i') }}</p>
</main>
<script>const code=decodeURIComponent(location.hash.slice(1));if(code){sessionStorage.setItem('aviato.gift_card_code',code);sessionStorage.setItem('aviato.gift_card_type',@js($campaign->requiresPayment() ? 'payment_required' : 'instant'));history.replaceState(null,'',location.pathname);}</script>
</body></html>
