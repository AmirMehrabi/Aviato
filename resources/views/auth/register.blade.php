<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $portal === 'admin' ? 'ثبت نام مدیر' : 'ثبت نام مشتری' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3efe6] text-slate-950">
    <main class="grid min-h-screen place-items-center px-4 py-12">
        <section class="w-full max-w-lg overflow-hidden rounded-[2rem] bg-white shadow-2xl shadow-[#0A3D37]/10 ring-1 ring-black/5">
            <div class="bg-[#0A3D37] px-8 py-7 text-white">
                <p class="text-sm font-bold text-emerald-100/80">{{ $portal === 'admin' ? 'پنل مدیران' : 'پنل مشتریان' }}</p>
                <h1 class="mt-2 text-3xl font-black">ثبت نام با ایمیل یا موبایل</h1>
            </div>

            <form method="POST" action="{{ route($portal.'.register.store', [], false) }}" class="space-y-5 px-8 py-8">
                @csrf

                <label class="block">
                    <span class="text-sm font-bold text-slate-700">نام</span>
                    <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none transition focus:border-[#105D52] focus:bg-white focus:ring-4 focus:ring-[#105D52]/10">
                    @error('name') <span class="mt-2 block text-sm font-semibold text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">ایمیل</span>
                        <input name="email" value="{{ old('email') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left outline-none transition focus:border-[#105D52] focus:bg-white focus:ring-4 focus:ring-[#105D52]/10" dir="ltr">
                        @error('email') <span class="mt-2 block text-sm font-semibold text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">موبایل</span>
                        <input name="phone" value="{{ old('phone') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left outline-none transition focus:border-[#105D52] focus:bg-white focus:ring-4 focus:ring-[#105D52]/10" dir="ltr">
                        @error('phone') <span class="mt-2 block text-sm font-semibold text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="text-sm font-bold text-slate-700">رمز عبور</span>
                    <input type="password" name="password" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left outline-none transition focus:border-[#105D52] focus:bg-white focus:ring-4 focus:ring-[#105D52]/10" dir="ltr">
                    @error('password') <span class="mt-2 block text-sm font-semibold text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-slate-700">تکرار رمز عبور</span>
                    <input type="password" name="password_confirmation" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left outline-none transition focus:border-[#105D52] focus:bg-white focus:ring-4 focus:ring-[#105D52]/10" dir="ltr">
                </label>

                <button class="w-full rounded-2xl bg-[#0A3D37] px-5 py-3.5 text-base font-black text-white shadow-lg shadow-[#0A3D37]/20 transition hover:bg-[#105D52]" type="submit">ایجاد حساب</button>
                <p class="text-center text-sm font-semibold text-slate-500">حساب دارید؟ <a class="text-[#0A3D37]" href="{{ route($portal.'.login', [], false) }}">ورود</a></p>
            </form>
        </section>
    </main>
</body>
</html>
