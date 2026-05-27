<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $portal === 'admin' ? 'ورود مدیران' : 'ورود مشتریان' }} | آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    @php
        $isAdminPortal = $portal === 'admin';
    @endphp

    <main class="min-h-screen px-4 py-6 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-7xl flex-col">
            <header class="flex h-14 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="آویاتو">
                    <span class="grid size-9 place-items-center rounded-lg bg-[#0069FF] text-sm font-black text-white">آ</span>
                    <span class="font-black">آویاتو</span>
                </a>
                @if (! $isAdminPortal)
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        ثبت نام مشتری
                    </a>
                @endif
            </header>

            <section class="grid flex-1 items-center gap-8 py-10 lg:grid-cols-[minmax(0,1fr)_440px] lg:py-14">
                <div class="hidden lg:block">
                    <p class="text-sm font-black text-[#0069FF]">{{ $isAdminPortal ? 'کنسول مدیریت آویاتو' : 'کنسول ابری مشتریان' }}</p>
                    <h1 class="mt-4 max-w-2xl text-5xl font-black leading-tight text-slate-950">
                        {{ $isAdminPortal ? 'مدیریت زیرساخت و فروش از یک پنل متمرکز.' : 'ساخت و مدیریت Droplet در کمتر از یک دقیقه.' }}
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-600">
                        {{ $isAdminPortal ? 'برای پیگیری سرورها، مشتریان، پلن ها و وضعیت provisioning وارد پنل مدیریت شوید.' : 'پس از ورود می توانید ماشین ها، کیف پول، بکاپ ها، مانیتورینگ و صورتحساب ها را از یک فضای واحد مدیریت کنید.' }}
                    </p>
                    <div class="mt-8 grid max-w-2xl grid-cols-3 gap-3">
                        @foreach ([
                            ['title' => 'NVMe', 'body' => 'دیسک سریع'],
                            ['title' => 'PAYG', 'body' => 'پرداخت مصرفی'],
                            ['title' => 'Backup', 'body' => 'بکاپ روزانه'],
                        ] as $item)
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p class="text-lg font-black text-slate-950">{{ $item['title'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $item['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                    <div class="border-b border-slate-200 bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_100%)] px-6 py-7 md:px-8">
                        <span class="inline-flex rounded-md bg-white px-3 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">
                            {{ $isAdminPortal ? 'پنل مدیران' : 'پنل مشتریان' }}
                        </span>
                        <h2 class="mt-4 text-3xl font-black leading-tight text-slate-950">ورود با ایمیل یا موبایل</h2>
                        <p class="mt-2 text-sm leading-7 text-slate-600">برای ادامه، اطلاعات حساب خود را وارد کنید.</p>
                    </div>

                    <form method="POST" action="{{ route($portal.'.login.store', [], false) }}" class="space-y-5 px-6 py-7 md:px-8" data-submit-loading>
                        @csrf

                        @if (session('status'))
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
                        @endif

                        <label class="block">
                            <span class="text-sm font-black text-slate-700">ایمیل یا شماره موبایل</span>
                            <input name="login" value="{{ old('login') }}" required autofocus class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                            @error('login') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-black text-slate-700">رمز عبور</span>
                            <input type="password" name="password" required class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10" dir="ltr">
                            @error('password') <span class="mt-2 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                            <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                            مرا به خاطر بسپار
                        </label>

                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]" type="submit">
                            <span>ورود</span>
                        </button>

                        @if (! $isAdminPortal)
                            <p class="text-center text-sm font-bold text-slate-500">
                                حساب ندارید؟
                                <a class="text-[#0069FF] transition hover:text-[#0050D0]" href="{{ route($portal.'.register', [], false) }}">ثبت نام مشتری</a>
                            </p>
                        @endif
                    </form>
                </section>
            </section>
        </div>
    </main>
</body>
<script>
    (function () {
        document.querySelectorAll('form[data-submit-loading]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = '<svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".3" stroke-width="3"></circle><path d="M22 12A10 10 0 0 0 12 2" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg><span>در حال بررسی...</span>';
            });
        });
    })();
</script>
</html>
