<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید OTP بازیابی رمز | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    @php
        $isSmsChannel = ($channel ?? 'email') === 'sms';
    @endphp
    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-lg items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] px-6 py-7 md:px-8">
                    <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">کد OTP بازیابی رمز</span>
                    <h1 class="mt-4 text-3xl font-black leading-tight text-slate-950">{{ $isSmsChannel ? 'کد پیامک شده را وارد کنید' : 'کد ایمیل شده را وارد کنید' }}</h1>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ $isSmsChannel ? 'کد ۶ رقمی OTP به شماره موبایل حساب مشتری ارسال شده است.' : 'کد ۶ رقمی OTP به ایمیل حساب مشتری ارسال شده است.' }}</p>
                </div>

                <form method="POST" action="{{ route('customer.password.verify', [], false) }}" class="space-y-5 px-6 py-7 md:px-8" data-submit-loading>
                    @csrf

                    @if (session('status'))
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
                    @endif

                    <input type="hidden" name="channel" value="{{ $isSmsChannel ? 'sms' : 'email' }}">

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">{{ $isSmsChannel ? 'موبایل مقصد OTP' : 'ایمیل مقصد OTP' }}</span>
                        <input name="login" value="{{ old('login', $login) }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                        @error('login') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">کد OTP شش رقمی</span>
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" value="{{ old('code') }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-4 text-center text-2xl font-black tracking-[0.35em] text-slate-950 outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                        @error('code') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                        <span>تایید OTP و ادامه</span>
                    </button>

                    <p class="text-center text-sm font-bold text-slate-500">
                        کد را دریافت نکردید؟
                        <a class="text-[#0069FF] transition hover:text-[#0050D0]" href="{{ route('customer.password.request', [], false) }}">ارسال کد جدید</a>
                    </p>
                </form>
            </section>
        </div>
    </main>
</body>
@include('auth.partials.submit-loading', ['loadingText' => 'در حال بررسی...'])
</html>
