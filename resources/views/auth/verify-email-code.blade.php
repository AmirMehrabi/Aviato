<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید OTP مشتری | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    @php
        $isSmsMode = ($mode ?? 'email') === 'sms';
    @endphp
    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-2xl items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] px-6 py-7 md:px-8">
                    <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">تایید OTP حساب مشتری</span>
                    <h1 class="mt-4 text-3xl font-black leading-tight text-slate-950">{{ $isSmsMode ? 'کد OTP پیامک شده را وارد کنید' : 'کد OTP ایمیل شده را وارد کنید' }}</h1>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ $isSmsMode ? 'کد ۶ رقمی OTP به موبایل شما ارسال شده است. این مرحله برای فعال شدن پنل مشتری اجباری است.' : 'کد ۶ رقمی OTP به ایمیل شما ارسال شده است. این مرحله برای فعال شدن پنل مشتری اجباری است.' }}</p>
                </div>

                <form method="POST" action="{{ route('customer.verification.verify', [], false) }}" class="space-y-5 px-6 py-7 md:px-8" data-submit-loading>
                    @csrf

                    @if (session('status'))
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
                    @endif

                    @if ($isSmsMode)
                        <label class="block">
                            <span class="text-sm font-black text-slate-700">موبایل مقصد OTP</span>
                            <input name="phone" value="{{ old('phone', $phone) }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                            @error('phone') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @else
                        <label class="block">
                            <span class="text-sm font-black text-slate-700">ایمیل مقصد OTP</span>
                            <input name="email" value="{{ old('email', $email) }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                            @error('email') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">کد OTP شش رقمی</span>
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" value="{{ old('code') }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-4 text-center text-2xl font-black tracking-[0.35em] text-slate-950 outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                        @error('code') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                        <span>تایید OTP و ورود</span>
                    </button>
                </form>

                <div class="border-t border-slate-200 px-6 py-5 md:px-8">
                    <form method="POST" action="{{ route('customer.verification.resend', [], false) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-submit-loading>
                        @csrf
                        @if ($isSmsMode)
                            <input type="hidden" name="phone" value="{{ old('phone', $phone) }}">
                        @else
                            <input type="hidden" name="email" value="{{ old('email', $email) }}">
                        @endif
                        <p class="text-sm font-bold text-slate-500">کد OTP را دریافت نکردید؟</p>
                        <button class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]" type="submit">
                            ارسال دوباره کد
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</body>
@include('auth.partials.submit-loading', ['loadingText' => 'در حال ارسال...'])
</html>
