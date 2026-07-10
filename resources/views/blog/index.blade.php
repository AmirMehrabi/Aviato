@extends('layouts.marketing')

@section('title', 'بلاگ آویاتو | مقالات زیرساخت ابری و سرور مجازی')
@section('description', 'مقاله‌ها و راهنماهای زیرساخت ابری، سرور مجازی و مدیریت سرویس‌های آنلاین توسط تیم آویاتو.')

@php
    $activePage = 'blog';
@endphp

@section('content')
    <div x-data="{ activeCategory: 'همه' }">
        <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-8 pt-14 md:px-8 md:pb-10 md:pt-20 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 text-xs font-black text-[#2C67C9]">
                            <span class="size-2 rounded-full bg-[#0069FF]"></span>
                            مرکز محتوای آویاتو
                        </div>
                        <h1 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">آخرین نوشته‌ها و راهنماها</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-8 text-slate-600 md:text-base">برای انتخاب بهتر، اجرای مطمئن‌تر و مدیریت ساده‌تر سرویس‌های آنلاین.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2" aria-label="فیلتر مقاله‌ها">
                        <span class="ml-1 text-xs font-bold text-slate-500">موضوع:</span>
                        <button type="button" @click="activeCategory = 'همه'" :class="activeCategory === 'همه' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'bg-white text-slate-600 hover:border-[#B8D6FF] hover:text-[#0069FF]'" class="rounded-full border border-slate-200 px-3.5 py-2 text-xs font-bold transition">همه</button>
                        @foreach ($categories as $category)
                            <button type="button" @click="activeCategory = @js($category)" :class="activeCategory === @js($category) ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'bg-white text-slate-600 hover:border-[#B8D6FF] hover:text-[#0069FF]'" class="rounded-full border border-slate-200 px-3.5 py-2 text-xs font-bold transition">{{ $category }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($featuredPost)
                    <article x-show="activeCategory === 'همه' || activeCategory === @js($featuredPost['category'])" class="group mt-8 overflow-hidden rounded-[2rem] border border-[#CFE2FF] bg-[#F7FBFF] shadow-xl shadow-slate-200/40">
                        <div class="grid lg:grid-cols-[1fr_0.85fr]">
                            <div class="flex flex-col justify-center p-6 md:p-9 lg:p-11">
                                <div class="flex flex-wrap items-center gap-3 text-xs font-bold text-slate-500">
                                    <span class="rounded-full bg-[#DDEBFF] px-3 py-1 font-black text-[#2C67C9]">جدیدترین مقاله</span>
                                    <span>{{ $featuredPost['category'] }}</span>
                                    <span class="text-slate-300">·</span>
                                    <span>{{ $featuredPost['date_display'] }}</span>
                                    <span class="text-slate-300">·</span>
                                    <span>{{ $featuredPost['reading_time'] }} مطالعه</span>
                                </div>
                                <h2 class="mt-4 text-2xl font-black leading-tight text-slate-950 md:text-4xl">{{ $featuredPost['title'] }}</h2>
                                <p class="mt-4 max-w-2xl text-sm leading-8 text-slate-600 md:text-base">{{ $featuredPost['excerpt'] }}</p>
                                <div class="mt-6 flex flex-wrap items-center gap-4">
                                    <a href="{{ route('blog.show', $featuredPost['slug']) }}" class="inline-flex items-center gap-2 rounded-xl bg-[#0069FF] px-5 py-3.5 text-sm font-black text-white transition hover:bg-[#0050D0] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/20">
                                        مطالعه جدیدترین مقاله
                                        <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                    <span class="text-xs font-bold text-slate-500">نوشته {{ $featuredPost['author'] }}</span>
                                </div>
                            </div>
                            <div class="relative min-h-52 overflow-hidden bg-gradient-to-br from-[#0069FF] via-[#4C86E8] to-[#CFE2FF] p-7 md:min-h-64">
                                @if ($featuredPost['cover_image'])
                                    <img src="{{ asset($featuredPost['cover_image']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-45">
                                @endif
                                <div aria-hidden="true" class="absolute -left-12 -top-12 size-52 rounded-full border-[22px] border-white/20"></div>
                                <div aria-hidden="true" class="absolute -bottom-24 -right-12 size-64 rounded-full border-[32px] border-white/15"></div>
                                <div class="relative flex h-full flex-col justify-between text-white">
                                    <span class="text-xs font-black uppercase tracking-[0.2em] text-white/75">LATEST / AVIATO</span>
                                    <p class="max-w-xs text-xl font-black leading-9">محتوایی برای تصمیم‌های بهتر در زیرساخت.</p>
                                </div>
                            </div>
                        </div>
                    </article>
                @endif
            </div>
        </section>

        <section class="px-4 pb-20 pt-8 md:px-8 md:pt-10 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#2C67C9]">بایگانی مقاله‌ها</p>
                        <h2 class="mt-2 text-2xl font-black text-slate-950">از اینجا ادامه دهید.</h2>
                    </div>
                    <p class="text-sm leading-7 text-slate-500">راهنماها و تجربه‌های کاربردی، مرتب‌شده بر اساس تاریخ انتشار.</p>
                </div>

                <div class="mt-7 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($regularPosts as $post)
                        <article x-show="activeCategory === 'همه' || activeCategory === @js($post['category'])" class="group flex flex-col overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-[#B8D6FF] hover:shadow-xl hover:shadow-slate-200/60">
                            <div class="relative h-28 overflow-hidden bg-gradient-to-br from-[#EEF5FF] to-[#DDEBFF] p-5">
                                @if ($post['cover_image'])
                                    <img src="{{ asset($post['cover_image']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-35">
                                @endif
                                <div aria-hidden="true" class="absolute -left-8 -top-12 size-36 rounded-full border-[16px] border-white/70"></div>
                                <span class="relative rounded-full bg-white/85 px-3 py-1 text-xs font-black text-[#2C67C9]">{{ $post['category'] }}</span>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex items-center gap-3 text-xs font-bold text-slate-400">
                                    <span>{{ $post['date_display'] }}</span>
                                    <span class="text-slate-300">·</span>
                                    <span>{{ $post['reading_time'] }} مطالعه</span>
                                </div>
                                <h3 class="mt-3 text-lg font-black leading-8 text-slate-950 transition group-hover:text-[#2C67C9]">{{ $post['title'] }}</h3>
                                <p class="mt-3 flex-1 text-sm leading-7 text-slate-600">{{ $post['excerpt'] }}</p>
                                <a href="{{ route('blog.show', $post['slug']) }}" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-[#2C67C9]">
                                    مطالعه مقاله
                                    <svg class="size-4 rotate-180 transition group-hover:-translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-[#F7FBFF] p-12 text-center md:col-span-2 lg:col-span-3">
                            <h3 class="text-xl font-black text-slate-950">فعلاً مقاله‌ای منتشر نشده است.</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">مقاله‌های جدید به‌زودی در این صفحه اضافه خواهند شد.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-14 grid gap-5 lg:grid-cols-[1fr_1.4fr]">
                    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-6">
                        <p class="text-sm font-black text-[#2C67C9]">چطور استفاده کنید؟</p>
                        <h2 class="mt-3 text-xl font-black leading-8 text-slate-950">موضوعی را انتخاب کنید و از همان‌جا شروع کنید.</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">مقاله‌ها برای پاسخ دادن به سؤال‌های واقعی قبل از خرید، هنگام اجرا و در زمان رشد سرویس نوشته شده‌اند.</p>
                    </div>
                    <div class="rounded-[1.5rem] bg-[#0F172A] p-6 text-white md:p-7">
                        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-black text-[#9CC4FF]">قدم بعدی</p>
                                <h2 class="mt-2 text-xl font-black leading-8">برای پروژه‌تان زیرساخت مناسب پیدا کنید.</h2>
                                <p class="mt-2 text-sm leading-7 text-slate-300">پلن‌ها را مقایسه کنید یا برای انتخاب بهتر با ما صحبت کنید.</p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row md:flex-col">
                                <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-[#EBF3FF]">دیدن پلن‌ها</a>
                                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-5 py-3 text-sm font-black text-white transition hover:bg-white/10">تماس با ما</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
@endsection
