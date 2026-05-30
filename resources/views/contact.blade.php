@extends('layouts.marketing')

@section('title', 'آویاتو: تماس با ما')
@section('description', 'تماس با آویاتو برای سوال درباره خرید سرور مجازی، انتخاب پلن، پشتیبانی و هماهنگی فروش.')

@php($activePage = 'contact')

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-16 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-4xl text-center">
            <p class="text-sm font-black text-[#0069FF]">تماس با تیم ما</p>
            <h1 class="mt-4 text-4xl font-black leading-tight md:text-5xl">برای خرید، انتخاب پلن یا پشتیبانی با ما در تماس باشید</h1>
            <p class="mt-6 text-lg leading-9 text-slate-600">اگر درباره سرور مجازی، قیمت ها یا انتخاب پلن سوال دارید، درخواست خود را ثبت کنید تا تیم آویاتو با شما هماهنگ کند.</p>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="space-y-5">
                @foreach ([
                    ['title' => 'فروش و راهنمای خرید', 'body' => 'admin@aviato.ir', 'meta' => 'شنبه تا چهارشنبه، ۹ تا ۱۸'],
                    ['title' => 'پشتیبانی فنی', 'body' => 'admin@aviato.ir', 'meta' => 'برای مشتریان فعال و سوالات فنی'],
                    ['title' => 'تماس تلفنی', 'body' => '۰۳۴-۹۱۰۹-۷۹۵۳', 'meta' => 'برای هماهنگی خرید و سوالات مهم'],
                    ['title' => 'آدرس دفتر', 'body' => 'کرمان، میدان قرنی، ساختمان پدر، واحد ۳۰۲', 'meta' => 'قرار ملاقات حضوری با هماهنگی قبلی'],
                ] as $contact)
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-black text-[#0069FF]">{{ $contact['title'] }}</p>
                        <p class="mt-3 text-2xl font-black text-slate-950">{{ $contact['body'] }}</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">{{ $contact['meta'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <h2 class="text-2xl font-black">فرم تماس</h2>
                <p class="mt-3 text-sm leading-8 text-slate-600">اطلاعات خودتان و موضوع درخواست را بنویسید تا بتوانیم بهتر راهنمایی تان کنیم.</p>

                @if (session('status'))
                    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold leading-7 text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" x-data="{ submitting: false }" x-on:submit="submitting = true" class="mt-8 grid gap-4 md:grid-cols-2">
                    @csrf
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">نام</span>
                        <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required placeholder="نام و نام خانوادگی" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                        @error('name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">ایمیل</span>
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="name@company.com" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                        @error('email') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">شماره تماس</span>
                        <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="مثلا 09123456789" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                        @error('phone') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">نوع نیاز</span>
                        <select name="need_type" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                            @foreach ($needTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('need_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('need_type') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">اندازه تیم</span>
                        <select name="team_size" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                            @foreach ($teamSizes as $value => $label)
                                <option value="{{ $value }}" @selected(old('team_size') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('team_size') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block md:col-span-2">
                        <span class="mb-2 block text-sm font-bold text-slate-700">توضیح درخواست</span>
                        <textarea rows="6" name="message" required placeholder="سوال، تعداد سرورهای مورد نیاز، زمان شروع یا توضیح کوتاه پروژه را بنویسید." class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">{{ old('message') }}</textarea>
                        @error('message') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <div class="md:col-span-2 flex flex-col items-start justify-between gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center">
                        <p class="text-xs font-bold text-slate-500">درخواست شما ثبت می شود و برای پیگیری با شما تماس می گیریم.</p>
                        <button type="submit" x-bind:disabled="submitting" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:opacity-70">
                            <span x-show="! submitting">ارسال درخواست</span>
                            <span x-cloak x-show="submitting">در حال ثبت...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
