@extends('layouts.marketing')

@section('title', 'بلاگ آویاتو | مقالات زیرساخت ابری و سرور مجازی')
@section('description', 'مقاله‌ها و راهنماهای زیرساخت ابری، سرور مجازی و مدیریت سرویس‌های آنلاین توسط تیم آویاتو.')

@php
    $activePage = 'blog';
@endphp

@section('content')
    <div x-data="{ activeCategory: 'همه' }">
    <section class="overflow-hidden bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-14 pt-16 md:px-8 md:pb-20 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-[#CFE2FF] bg-white/80 px-4 py-2 text-xs font-black text-[#2C67C9] shadow-sm">
                    <span class="size-2 rounded-full bg-[#0069FF]"></span>
                    بلاگ آویاتو
                </div>
                <h1 class="mt-6 text-4xl font-black leading-tight text-slate-950 md:text-6xl">برای ساختن، اجرا کردن و رشد دادن سرویس آنلاین.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-9 text-slate-600">راهنماها، تجربه‌ها و توضیح‌های ساده‌ای درباره زیرساخت ابری، سرور مجازی و مدیریت سرویس‌های آنلاین.</p>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-2">
                <span class="ml-2 text-sm font-bold text-slate-500">دسته‌بندی:</span>
                <button type="button" @click="activeCategory = 'همه'" :class="activeCategory === 'همه' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'bg-white text-slate-600 hover:border-[#B8D6FF] hover:text-[#0069FF]'" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold transition">همه مقاله‌ها</button>
                @foreach ($categories as $category)
                    <button type="button" @click="activeCategory = @js($category)" :class="activeCategory === @js($category) ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'bg-white text-slate-600 hover:border-[#B8D6FF] hover:text-[#0069FF]'" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold transition">{{ $category }}</button>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            @if ($featuredPost)
                <article x-show="activeCategory === 'همه' || activeCategory === @js($featuredPost['category'])" class="group overflow-hidden rounded-[2rem] border border-[#CFE2FF] bg-[#F7FBFF] shadow-xl shadow-slate-200/40">
                    <div class="grid lg:grid-cols-[1.05fr_0.95fr]">
                        <div class="flex flex-col justify-center p-7 md:p-12">
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                <span class="rounded-full bg-[#DDEBFF] px-3 py-1 text-xs font-black text-[#2C67C9]">مقاله پیشنهادی</span>
                                <span>{{ $featuredPost['category'] }}</span>
                                <span class="text-slate-300">·</span>
                                <span>{{ $featuredPost['date_display'] }}</span>
                            </div>
                            <h2 class="mt-5 text-3xl font-black leading-tight text-slate-950 md:text-4xl">{{ $featuredPost['title'] }}</h2>
                            <p class="mt-5 text-base leading-8 text-slate-600 md:text-lg">{{ $featuredPost['excerpt'] }}</p>
                            <div class="mt-7 flex flex-wrap items-center gap-4">
                                <a href="{{ route('blog.show', $featuredPost['slug']) }}" class="inline-flex items-center gap-2 rounded-xl bg-[#0069FF] px-5 py-3.5 text-sm font-black text-white transition hover:bg-[#0050D0] focus:outline-none focus:ring-4 focus:ring-[#0069FF]/20">
                                    مطالعه مقاله
                                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                                <span class="text-sm font-bold text-slate-500">{{ $featuredPost['reading_time'] }} مطالعه</span>
                            </div>
                        </div>
                        <div class="relative min-h-64 overflow-hidden bg-gradient-to-br from-[#0069FF] via-[#4C86E8] to-[#CFE2FF] p-8 md:min-h-80">
                            @if ($featuredPost['cover_image'])
                                <img src="{{ asset($featuredPost['cover_image']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-45">
                            @endif
                            <div aria-hidden="true" class="absolute -left-12 -top-12 size-56 rounded-full border-[24px] border-white/20"></div>
                            <div aria-hidden="true" class="absolute -bottom-24 -right-16 size-72 rounded-full border-[40px] border-white/15"></div>
                            <div class="relative flex h-full flex-col justify-between text-white">
                                <span class="text-6xl font-black opacity-30">۰۱</span>
                                <div>
                                    <p class="text-sm font-bold text-white/75">نگاه آویاتو</p>
                                    <p class="mt-2 max-w-xs text-2xl font-black leading-10">زیرساخت خوب، مسیر را برای کار اصلی شما باز می‌کند.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @endif

            <div class="mt-14 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-black text-[#2C67C9]">همه نوشته‌ها</p>
                    <h2 class="mt-2 text-3xl font-black text-slate-950">چیزی برای شروع پیدا کنید.</h2>
                </div>
                <p class="text-sm leading-7 text-slate-500">از راهنمای خرید تا تجربه‌های واقعی ساخت و نگهداری سرویس.</p>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($regularPosts as $post)
                    <article x-show="activeCategory === 'همه' || activeCategory === @js($post['category'])" class="group flex flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-[#B8D6FF] hover:shadow-xl hover:shadow-slate-200/60">
                        <div class="relative h-32 overflow-hidden bg-gradient-to-br from-[#EEF5FF] to-[#DDEBFF] p-5">
                            @if ($post['cover_image'])
                                <img src="{{ asset($post['cover_image']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-35">
                            @endif
                            <div aria-hidden="true" class="absolute -left-8 -top-12 size-40 rounded-full border-[18px] border-white/70"></div>
                            <span class="relative rounded-full bg-white/80 px-3 py-1 text-xs font-black text-[#2C67C9]">{{ $post['category'] }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex items-center gap-3 text-xs font-bold text-slate-400">
                                <span>{{ $post['date_display'] }}</span>
                                <span class="text-slate-300">·</span>
                                <span>{{ $post['reading_time'] }} مطالعه</span>
                            </div>
                            <h3 class="mt-4 text-xl font-black leading-9 text-slate-950 transition group-hover:text-[#2C67C9]">{{ $post['title'] }}</h3>
                            <p class="mt-3 flex-1 text-sm leading-8 text-slate-600">{{ $post['excerpt'] }}</p>
                            <a href="{{ route('blog.show', $post['slug']) }}" class="mt-6 inline-flex items-center gap-2 text-sm font-black text-[#2C67C9]">
                                مطالعه مقاله
                                <svg class="size-4 rotate-180 transition group-hover:-translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-slate-200 bg-[#F7FBFF] p-12 text-center md:col-span-2 lg:col-span-3">
                        <h3 class="text-xl font-black text-slate-950">فعلاً مقاله‌ای منتشر نشده است.</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">مقاله‌های جدید به‌زودی در این صفحه اضافه خواهند شد.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-16 overflow-hidden rounded-[2rem] bg-[#0F172A] p-7 text-white md:p-10">
                <div class="grid gap-7 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-sm font-black text-[#9CC4FF]">آماده شروع هستید؟</p>
                        <h2 class="mt-3 text-2xl font-black leading-10 md:text-3xl">اگر سؤال شما جوابش را اینجا ندارد، ما کنارتان هستیم.</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-8 text-slate-300">پلن‌ها را مقایسه کنید یا برای انتخاب زیرساخت مناسب پروژه‌تان با ما صحبت کنید.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                        <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-black text-slate-950 transition hover:bg-[#EBF3FF]">دیدن پلن‌ها</a>
                        <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-6 py-3.5 text-sm font-black text-white transition hover:bg-white/10">تماس با ما</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
@endsection
