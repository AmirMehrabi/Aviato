<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ثبت نام مشتری | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    @php
        $verificationModeValue = $verificationMode ?? 'email';
        $isCustomerEmailMode = ($verificationMode ?? 'email') === 'email';
        $isCustomerSmsMode = ($verificationMode ?? 'email') === 'sms';
    @endphp

    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-7xl flex-col">
            <header class="flex h-14 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="آویاتو">
                    <img src="{{ asset('assets/images/aviato_logo_full_color.png') }}" alt="آویاتو" class="h-10 w-auto object-contain object-right">
                </a>
                <a href="{{ route('customer.login', [], false) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                    ورود
                </a>
            </header>

            <section class="grid flex-1 items-center gap-8 py-10 lg:grid-cols-[minmax(0,1fr)_520px] lg:py-14">
                <div class="hidden lg:block">
                    <p class="text-sm font-black text-[#0069FF]">شروع خرید Droplet</p>
                    <h1 class="mt-4 max-w-2xl text-5xl font-black leading-tight text-slate-950">
                        حساب بسازید، کیف پول را شارژ کنید و سرور آماده تحویل بگیرید.
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-600">
                        ثبت نام مسیر مستقیم خرید است؛ پلن را انتخاب می کنید، منابع را می بینید و ماشین از پنل مشتری ساخته می شود.
                    </p>
                    <div class="mt-8 overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-2xl shadow-[#031B4E]/20">
                        <p class="text-sm font-black text-[#8FC7FF]">آنچه بعد از ثبت نام دارید</p>
                        <div class="mt-5 grid gap-3 text-sm font-bold text-[#C7D4EA]">
                            @foreach (['مدیریت ماشین های ابری', 'کیف پول و پرداخت براساس میزان مصرف', 'بکاپ، مانیتورینگ و مدیریت صورت‌حساب‌ها'] as $item)
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-[#0069FF]"></span>
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                    <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] p-4 md:px-8">
                        {{-- <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">پنل مشتریان</span> --}}
                        <h2 class="text-3xl font-black leading-tight text-slate-950">ثبت نام</h2>
                        {{-- <p class="mt-2 text-sm leading-7 text-slate-600">{{ $isCustomerSmsMode ? 'بعد از ثبت نام، کد OTP با پیامک برای موبایل شما ارسال می شود.' : ($verificationModeValue === 'disabled' ? 'تایید حساب مشتری غیرفعال است و پس از ثبت نام می‌توانید وارد شوید.' : 'بعد از ثبت نام، کد OTP به ایمیل شما ارسال می شود.') }}</p> --}}
                    </div>

                    <form method="POST" action="{{ route($portal.'.register.store', [], false) }}" class="space-y-5 p-4 md:px-8" data-submit-loading>
                        @csrf

                        @if (session('status'))
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
                        @endif

                        <label class="block">
                            <span class="text-sm font-black text-slate-700">نام</span>
                            <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10">
                            @error('name') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-black text-slate-700">ایمیل</span>
                                <input name="email" value="{{ old('email') }}" @required($isCustomerEmailMode) class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                                @error('email') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="text-sm font-black text-slate-700">موبایل</span>
                                <input name="phone" value="{{ old('phone') }}" @required($isCustomerSmsMode) class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                                @error('phone') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-sm font-black text-slate-700">رمز عبور</span>
                            <input type="password" name="password" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                            @error('password') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-black text-slate-700">تکرار رمز عبور</span>
                            <input type="password" name="password_confirmation" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                        </label>

                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                            <span>{{ $verificationModeValue === 'disabled' ? 'ایجاد حساب' : 'ایجاد حساب و ارسال OTP' }}</span>
                        </button>

                        <p class="text-center text-sm font-bold text-slate-500">
                            حساب دارید؟
                            <a class="text-[#0069FF] transition hover:text-[#0050D0]" href="{{ route($portal.'.login', [], false) }}">ورود</a>
                        </p>
                    </form>
                </section>
            </section>
        </div>
    </main>
</body>
@include('auth.partials.submit-loading', ['loadingText' => 'در حال ارسال...'])
</html>
