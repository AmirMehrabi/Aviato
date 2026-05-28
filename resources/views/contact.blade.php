@extends('layouts.marketing')

@section('title', 'تماس با ما | آویاتو')
@section('description', 'صفحه تماس با آویاتو برای درخواست دمو، مشاوره فنی و سوالات فروش.')

@php($activePage = 'contact')

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-16 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-4xl text-center">
            <p class="text-sm font-black text-[#0069FF]">تماس با تیم ما</p>
            <h1 class="mt-4 text-4xl font-black leading-tight md:text-5xl">برای مشاوره، دمو یا شروع همکاری در تماس باشید</h1>
            <p class="mt-6 text-lg leading-9 text-slate-600">داده های این صفحه نمونه هستند، اما ساختار آن برای تیم فروش و پشتیبانی قابل استفاده طراحی شده است.</p>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="space-y-5">
                @foreach ([
                    ['title' => 'فروش و پیش فروش', 'body' => 'sales@avieto.ir', 'meta' => 'شنبه تا چهارشنبه، ۹ تا ۱۸'],
                    ['title' => 'پشتیبانی فنی', 'body' => 'support@avieto.ir', 'meta' => 'برای مشتریان فعال و تیکت های فنی'],
                    ['title' => 'تماس مستقیم', 'body' => '۰۳۴-۱۲۳۴۵۶۷۸', 'meta' => 'پاسخ گویی نمونه برای تماس های تجاری'],
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
                <h2 class="text-2xl font-black">فرم درخواست مشاوره</h2>
                <p class="mt-3 text-sm leading-8 text-slate-600">یک فرم نمایشی برای جمع آوری نیاز اولیه مشتری. بعدا می توان آن را به backend متصل کرد.</p>
                <form class="mt-8 grid gap-4 md:grid-cols-2">
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">نام</span>
                        <input type="text" placeholder="مثلا امیر رضایی" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">ایمیل کاری</span>
                        <input type="email" placeholder="name@company.com" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">نوع نیاز</span>
                        <select class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                            <option>زیرساخت SaaS</option>
                            <option>فروشگاه آنلاین</option>
                            <option>دیتابیس و بکاپ</option>
                            <option>مشاوره مهاجرت</option>
                        </select>
                    </label>
                    <label class="block md:col-span-1">
                        <span class="mb-2 block text-sm font-bold text-slate-700">اندازه تیم</span>
                        <select class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white">
                            <option>۱ تا ۵ نفر</option>
                            <option>۶ تا ۲۰ نفر</option>
                            <option>۲۱ تا ۵۰ نفر</option>
                            <option>بیشتر از ۵۰ نفر</option>
                        </select>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="mb-2 block text-sm font-bold text-slate-700">شرح نیاز</span>
                        <textarea rows="6" placeholder="مثلا به ۳ سرور application، یک دیتابیس replica و بکاپ روزانه نیاز داریم..." class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-[#0069FF] focus:bg-white"></textarea>
                    </label>
                    <div class="md:col-span-2 flex flex-col items-start justify-between gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center">
                        <p class="text-xs font-bold text-slate-500">این فرم نمایشی است و هنوز ارسال واقعی انجام نمی دهد.</p>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ارسال درخواست نمونه</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
