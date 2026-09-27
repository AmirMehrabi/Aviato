@extends('layouts.marketing')

@section('title', 'آویاتو: تماس با ما')
@section('description', 'برای مشاوره خرید، انتخاب پلن یا پشتیبانی سرویس‌های آویاتو راه ارتباطی مناسب را پیدا کنید.')
@section('canonical', route('contact'))

@php($activePage = 'contact')

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-10 pt-16 md:px-8 md:pb-14 md:pt-24">
        <div class="mx-auto max-w-5xl">
            <p class="text-sm font-black text-[#0069FF]">تماس با آویاتو</p>
            <h1 class="mt-4 max-w-3xl text-4xl font-black leading-tight text-slate-950 md:text-5xl">برای خرید یا پشتیبانی، از مسیر مناسب شروع کنید</h1>
            <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 md:text-lg md:leading-9">برای انتخاب سرویس و مشاوره خرید درخواست بفرستید. اگر مشتری آویاتو هستید، پشتیبانی سرویس‌هایتان را از پنل مشتری پیگیری کنید.</p>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 md:pb-28">
        <div class="mx-auto max-w-5xl">
            @if (session('status'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold leading-7 text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold leading-7 text-red-800" role="alert">
                    لطفاً موارد مشخص‌شده در <a href="#sales-request" class="underline underline-offset-4">فرم درخواست</a> را بررسی کنید.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <a href="#sales-request" class="group rounded-2xl border border-[#B8D6FF] bg-[#F7FBFF] p-5 transition hover:border-[#0069FF] hover:bg-[#EEF5FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] md:p-6">
                    <span class="text-xs font-black text-[#0069FF]">خرید و مشاوره</span>
                    <h2 class="mt-2 text-xl font-black text-slate-950">برای انتخاب پلن راهنمایی می‌خواهم</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">نیازتان را بنویسید تا تیم آویاتو برای انتخاب سرویس و شروع کار با شما هماهنگ کند.</p>
                    <span class="mt-4 inline-flex text-sm font-black text-[#0069FF] group-hover:underline">تکمیل فرم درخواست ←</span>
                </a>
                <a href="{{ route('customer.tickets.create') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-[#0069FF] hover:bg-[#F7FBFF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] md:p-6">
                    <span class="text-xs font-black text-[#0069FF]">پشتیبانی مشتریان</span>
                    <h2 class="mt-2 text-xl font-black text-slate-950">درباره سرویس فعالم کمک می‌خواهم</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">در پنل مشتری تیکت ثبت کنید و پاسخ و روند پیگیری را همان‌جا ببینید.</p>
                    <span class="mt-4 inline-flex text-sm font-black text-[#0069FF] group-hover:underline">رفتن به ثبت تیکت ←</span>
                </a>
            </div>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(260px,0.8fr)]">
                <section id="sales-request" class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-8" aria-labelledby="sales-request-title">
                    <h2 id="sales-request-title" class="text-2xl font-black text-slate-950">درخواست مشاوره خرید</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">اطلاعات تماس و توضیح کوتاهی از نیازتان را بنویسید تا بتوانیم بهتر راهنمایی‌تان کنیم.</p>

                    <form method="POST" action="{{ route('contact.store') }}" x-data="{ submitting: false }" x-on:submit="submitting = true" class="mt-7 space-y-5">
                        @csrf
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">نام و نام خانوادگی <span class="text-red-600">*</span></span>
                            <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required aria-required="true" @error('name') aria-invalid="true" @enderror class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">
                            @error('name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">ایمیل <span class="text-red-600">*</span></span>
                            <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" dir="ltr" required aria-required="true" @error('email') aria-invalid="true" @enderror placeholder="name@company.com" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-base outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">
                            @error('email') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">شماره تماس <span class="font-normal text-slate-500">(اختیاری)</span></span>
                            <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" dir="ltr" placeholder="09123456789" @error('phone') aria-invalid="true" @enderror class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-base outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">
                            @error('phone') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">نوع درخواست <span class="text-red-600">*</span></span>
                            <select name="need_type" required aria-required="true" @error('need_type') aria-invalid="true" @enderror class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">
                                <option value="" disabled @selected(! old('need_type'))>نوع درخواست را انتخاب کنید</option>
                                @foreach ($needTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('need_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('need_type') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">اندازه تیم <span class="font-normal text-slate-500">(اختیاری)</span></span>
                            <select name="team_size" @error('team_size') aria-invalid="true" @enderror class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">
                                <option value="" @selected(! old('team_size'))>ترجیح می‌دهم مشخص نکنم</option>
                                @foreach ($teamSizes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('team_size') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('team_size') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-bold text-slate-700">توضیح درخواست <span class="text-red-600">*</span></span>
                            <textarea rows="5" name="message" required aria-required="true" @error('message') aria-invalid="true" @enderror placeholder="مثلاً تعداد سرورها، زمان شروع یا پرسش‌تان درباره پلن‌ها" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base leading-7 outline-none transition focus:border-[#0069FF] focus:ring-2 focus:ring-[#DCEAFF]">{{ old('message') }}</textarea>
                            @error('message') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="border-t border-slate-100 pt-5">
                            <p class="mb-4 text-sm leading-7 text-slate-500">درخواست شما ثبت می‌شود و برای پیگیری از راه اطلاعاتی که وارد کرده‌اید با شما تماس می‌گیریم.</p>
                            <button type="submit" x-bind:disabled="submitting" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto">
                                <span x-show="! submitting">ارسال درخواست</span>
                                <span x-cloak x-show="submitting">در حال ثبت...</span>
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="rounded-3xl border border-slate-200 bg-white p-5 md:p-7" aria-labelledby="contact-details-title">
                    <h2 id="contact-details-title" class="text-xl font-black text-slate-950">راه‌های دیگر ارتباط</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-600">برای هماهنگی خرید، از راه‌های زیر هم می‌توانید با ما در تماس باشید.</p>
                    <dl class="mt-5 divide-y divide-slate-100">
                        <div class="py-4 first:pt-0">
                            <dt class="text-sm font-bold text-slate-500">ایمیل</dt>
                            <dd class="mt-2 break-all text-base font-black text-[#0069FF]"><a href="mailto:admin@aviato.ir" dir="ltr" class="hover:underline">admin@aviato.ir</a></dd>
                        </div>
                        <div class="py-4">
                            <dt class="text-sm font-bold text-slate-500">تلفن</dt>
                            <dd class="mt-2 text-base font-black text-[#0069FF]"><a href="tel:+983491097953" dir="ltr" class="hover:underline">۰۳۴-۹۱۰۹-۷۹۵۳</a></dd>
                        </div>
                        <div class="py-4">
                            <dt class="text-sm font-bold text-slate-500">ساعت پاسخ‌گویی</dt>
                            <dd class="mt-2 text-sm leading-7 text-slate-700">شنبه تا چهارشنبه، ۹ تا ۱۸</dd>
                        </div>
                        <div class="pt-4">
                            <dt class="text-sm font-bold text-slate-500">آدرس دفتر</dt>
                            <dd class="mt-2 text-sm leading-7 text-slate-700">کرمان، میدان قرنی، ساختمان پدر، واحد ۳۰۲</dd>
                            <dd class="mt-1 text-xs leading-6 text-slate-500">مراجعه حضوری با هماهنگی قبلی</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </div>
    </section>
@endsection
