<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود با کد یک‌بارمصرف | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-lg items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] px-6 py-7 md:px-8">
                    <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">ورود سریع و امن</span>
                    <h1 class="mt-4 text-3xl font-black leading-tight text-slate-950">ورود با کد یک‌بارمصرف</h1>
                    <p class="mt-2 text-sm leading-7 text-slate-600">ایمیل یا شماره موبایل حساب‌تان را وارد کنید. کد ورود را بدون نیاز به رمز عبور برایتان ارسال می‌کنیم.</p>
                </div>

                <form method="POST" action="{{ route('customer.login.otp.send', [], false) }}" class="space-y-5 px-6 py-7 md:px-8" data-submit-loading>
                    @csrf
                    @include('auth.partials.validation-errors')

                    <label class="block">
                        <span class="text-sm font-black text-slate-700">شماره موبایل</span>
                        <input name="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="۰۹۱۲" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-left text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                    </label>

                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                        ارسال کد ورود
                    </button>

                    <div class="rounded-xl border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-center text-xs font-bold leading-6 text-[#1856A8]">
                       کد به شماره موبایل شما ارسال می‌شود.
                    </div>

                    <p class="text-center text-sm font-bold text-slate-500">
                        ورود با رمز عبور را ترجیح می‌دهید؟
                        <a class="text-[#0069FF] transition hover:text-[#0050D0]" href="{{ route('customer.login', [], false) }}">ورود با رمز عبور</a>
                    </p>
                </form>
            </section>
        </div>
    </main>
</body>
@include('auth.partials.submit-loading', ['loadingText' => 'در حال ارسال کد...'])
</html>
