<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید کد ورود | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-lg items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] px-6 py-7 md:px-8">
                    <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">تایید ورود</span>
                    <h1 class="mt-4 text-3xl font-black leading-tight text-slate-950">کد ورود را وارد کنید</h1>
                    @if ($maskedDestination)
                        <p class="mt-2 text-sm leading-7 text-slate-600">کد ۶ رقمی به <strong dir="ltr" class="text-slate-900">{{ $maskedDestination }}</strong> ارسال شد.</p>
                    @else
                        <p class="mt-2 text-sm leading-7 text-slate-600">اگر اطلاعات حساب صحیح باشد، کد ورود برای شما ارسال شده است.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('customer.login.otp.verify.store', [], false) }}" class="space-y-5 px-6 py-7 md:px-8" data-submit-loading>
                    @csrf
                    @if (session('status'))
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
                    @endif
                    @include('auth.partials.validation-errors')

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">کد ۶ رقمی</span>
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" required autofocus class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-4 text-center text-3xl font-black tracking-[0.4em] text-slate-950 outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                    </label>

                    @if ($expiresAt)
                        <p class="text-center text-xs font-bold text-slate-500">کد تا ۱۰ دقیقه معتبر است.</p>
                    @endif

                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                        تایید کد و ورود
                    </button>
                </form>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between md:px-8">
                    @if ($maskedDestination)
                        <form method="POST" action="{{ route('customer.login.otp.resend', [], false) }}" data-submit-loading x-data="{ remaining: {{ $resendAvailableAt ? max(0, $resendAvailableAt->diffInSeconds(now())) : 0 }}, timer: null }" x-init="timer = setInterval(() => { if (remaining > 0) remaining--; else clearInterval(timer) }, 1000)">
                            @csrf
                            <button type="submit" :disabled="remaining > 0" class="text-sm font-black text-[#0069FF] transition hover:text-[#0050D0] disabled:cursor-not-allowed disabled:text-slate-400">
                                <span x-show="remaining === 0">ارسال دوباره کد</span>
                                <span x-show="remaining > 0" x-cloak>ارسال دوباره کد (<span x-text="remaining"></span> ثانیه)</span>
                            </button>
                        </form>
                    @else
                        <span class="text-xs font-bold text-slate-500">کد را دریافت نکردید؟ اطلاعات را دوباره بررسی کنید.</span>
                    @endif
                    <a class="text-sm font-black text-slate-600 transition hover:text-[#0069FF]" href="{{ route('customer.login.otp', [], false) }}">تغییر ایمیل یا موبایل</a>
                </div>
            </section>
        </div>
    </main>
</body>
@include('auth.partials.submit-loading', ['loadingText' => 'در حال بررسی کد...'])
</html>
