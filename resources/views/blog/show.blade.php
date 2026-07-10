@extends('layouts.marketing')

@section('title', $post['title'] . ' | بلاگ آویاتو')
@section('description', $post['excerpt'])

@php
    $activePage = 'blog';
@endphp

@section('content')
    <div x-data="{ progress: 0 }" x-init="window.addEventListener('scroll', () => { const article = document.querySelector('[data-blog-article]'); if (!article) return; const total = article.offsetHeight - window.innerHeight; progress = Math.min(100, Math.max(0, ((window.scrollY - article.offsetTop + 120) / total) * 100)); })">
        <div class="fixed inset-x-0 top-0 z-[70] h-1 bg-slate-100/80" aria-hidden="true"><div class="h-full bg-[#0069FF] transition-[width] duration-150" :style="`width: ${progress}%`"></div></div>

        <section class="overflow-hidden bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-10 pt-14 md:px-8 md:pb-14 md:pt-20 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-sm font-black text-[#2C67C9] transition hover:text-[#0069FF]">
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    بازگشت به بلاگ
                </a>

                <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
                    <div class="max-w-4xl">
                        <div class="flex flex-wrap items-center gap-3 text-sm font-bold text-slate-500">
                            <span class="rounded-full bg-[#DDEBFF] px-3 py-1 text-xs font-black text-[#2C67C9]">{{ $post['category'] }}</span>
                            <span>{{ $post['date_display'] }}</span>
                            <span class="text-slate-300">·</span>
                            <span>{{ $post['reading_time'] }} مطالعه</span>
                        </div>
                        <h1 class="mt-5 text-3xl font-black leading-tight text-slate-950 md:text-5xl md:leading-[1.35]">{{ $post['title'] }}</h1>
                        <p class="mt-6 max-w-3xl text-lg leading-9 text-slate-600">{{ $post['excerpt'] }}</p>

                        <div class="mt-7 flex flex-wrap items-center gap-4 border-t border-slate-200/80 pt-6">
                            <span class="flex size-11 items-center justify-center rounded-full bg-[#DDEBFF] text-sm font-black text-[#2C67C9]">آ</span>
                            <div class="text-sm">
                                <p class="font-black text-slate-950">{{ $post['author'] }}</p>
                                <p class="mt-1 text-slate-500">نوشته شده در {{ $post['date_display'] }}@if ($post['updated_date']) · به‌روزرسانی {{ $post['updated_date'] }}@endif</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative hidden min-h-52 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0069FF] via-[#4C86E8] to-[#CFE2FF] p-6 lg:block">
                        @if ($post['cover_image'])
                            <img src="{{ asset($post['cover_image']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-45">
                        @endif
                        <div aria-hidden="true" class="absolute -left-10 -top-10 size-44 rounded-full border-[20px] border-white/20"></div>
                        <div aria-hidden="true" class="absolute -bottom-20 -right-10 size-56 rounded-full border-[28px] border-white/15"></div>
                        <div class="relative flex h-full flex-col justify-between text-white">
                            <span class="text-xs font-black uppercase tracking-[0.2em] text-white/70">AVIATO / BLOG</span>
                            <span class="text-2xl font-black leading-9">یادگیری، انتخاب بهتر و اجرای مطمئن‌تر.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 pb-20 md:px-8 lg:px-10">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                @if (count($post['toc']) > 0)
                    <aside class="order-first lg:sticky lg:top-8 lg:order-last">
                        <div class="rounded-[1.5rem] border border-slate-200 bg-[#F7FBFF] p-5">
                            <p class="text-sm font-black text-slate-950">در این مقاله</p>
                            <nav class="mt-4 grid gap-2" aria-label="فهرست مقاله">
                                @foreach ($post['toc'] as $heading)
                                    <a href="#{{ $heading['id'] }}" class="block rounded-lg px-3 py-2 text-sm leading-6 text-slate-600 transition hover:bg-white hover:text-[#0069FF] {{ $heading['level'] === 3 ? 'pr-6 text-xs' : 'font-bold' }}">{{ $heading['label'] }}</a>
                                @endforeach
                            </nav>
                        </div>
                    </aside>
                @endif

                <article data-blog-article class="min-w-0" dir="rtl">
                    <div class="mb-8 flex flex-wrap items-center gap-3" x-data="{ copied: false }">
                        <span class="text-sm font-bold text-slate-500">این مقاله را به اشتراک بگذارید:</span>
                        <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(@js(url()->current())).then(() => { copied = true; setTimeout(() => copied = false, 1800) })" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#F7FBFF] hover:text-[#0069FF]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="10" height="10" rx="2"/><path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/></svg>
                            <span x-text="copied ? 'لینک کپی شد' : 'کپی لینک مقاله'"></span>
                        </button>
                    </div>

                    <div class="blog-content text-base leading-[2.15] text-slate-700">
                        {!! $post['content'] !!}
                    </div>

                    <div class="mt-12 flex flex-wrap gap-2 border-t border-slate-200 pt-6">
                        @foreach ($post['tags'] as $tag)
                            <span class="rounded-full bg-[#EEF5FF] px-3 py-1.5 text-xs font-bold text-[#2C67C9]">#{{ $tag }}</span>
                        @endforeach
                    </div>
                </article>
            </div>

            @if (count($relatedPosts) > 0)
                <div class="mx-auto mt-16 max-w-7xl border-t border-slate-200 pt-12">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="text-sm font-black text-[#2C67C9]">ادامه مطالعه</p>
                            <h2 class="mt-2 text-3xl font-black text-slate-950">مطالب مرتبط</h2>
                        </div>
                        <a href="{{ route('blog') }}" class="text-sm font-black text-[#2C67C9]">مشاهده همه مقاله‌ها</a>
                    </div>
                    <div class="mt-7 grid gap-5 md:grid-cols-3">
                        @foreach ($relatedPosts as $relatedPost)
                            <a href="{{ route('blog.show', $relatedPost['slug']) }}" class="group flex flex-col rounded-[1.5rem] border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-[#B8D6FF] hover:shadow-xl hover:shadow-slate-200/50">
                                <div class="flex items-center gap-3 text-xs font-bold text-slate-400">
                                    <span class="rounded-full bg-[#EEF5FF] px-3 py-1 text-[#2C67C9]">{{ $relatedPost['category'] }}</span>
                                    <span>{{ $relatedPost['reading_time'] }} مطالعه</span>
                                </div>
                                <h3 class="mt-4 text-lg font-black leading-8 text-slate-950 transition group-hover:text-[#2C67C9]">{{ $relatedPost['title'] }}</h3>
                                <span class="mt-5 text-sm font-black text-[#2C67C9]">مطالعه مقاله ←</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mx-auto mt-16 max-w-7xl overflow-hidden rounded-[2rem] bg-[#EEF5FF] p-7 md:p-10">
                <div class="grid gap-7 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <h2 class="text-2xl font-black text-slate-950 md:text-3xl">برای اجرای پروژه‌تان آماده‌اید؟</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-8 text-slate-600">پلن‌های آویاتو را ببینید یا اگر برای انتخاب سرور سؤال دارید، با ما صحبت کنید.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-6 py-3.5 text-sm font-black text-white transition hover:bg-[#0050D0]">دیدن پلن‌ها</a>
                        <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:text-[#0069FF]">تماس با ما</a>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .blog-content h1 { display: none; }
        .blog-content h2 { margin-top: 3rem; margin-bottom: 1rem; color: #0f172a; font-size: 1.65rem; font-weight: 900; line-height: 1.55; scroll-margin-top: 2rem; }
        .blog-content h3 { margin-top: 2rem; margin-bottom: .75rem; color: #0f172a; font-size: 1.25rem; font-weight: 800; line-height: 1.6; scroll-margin-top: 2rem; }
        .blog-content p { margin: 1.35rem 0; }
        .blog-content strong { color: #0f172a; font-weight: 800; }
        .blog-content a { color: #2C67C9; font-weight: 700; text-decoration: underline; text-decoration-color: #B8D6FF; text-underline-offset: 3px; }
        .blog-content a:hover { color: #0069FF; }
        .blog-content ul, .blog-content ol { margin: 1.35rem 0; padding-right: 1.6rem; }
        .blog-content li { margin-top: .6rem; padding-right: .25rem; }
        .blog-content blockquote { margin: 2rem 0; border-right: 4px solid #2C67C9; border-radius: .5rem; background: #F7FBFF; padding: 1rem 1.25rem; color: #475569; }
        .blog-content table { width: 100%; margin: 2rem 0; overflow: hidden; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 1rem; }
        .blog-content th, .blog-content td { padding: .85rem 1rem; border-bottom: 1px solid #e2e8f0; text-align: right; }
        .blog-content tr:last-child td { border-bottom: 0; }
        .blog-content th { background: #F7FBFF; color: #0f172a; font-weight: 800; }
        .blog-content code { border-radius: .35rem; background: #f1f5f9; padding: .15rem .4rem; font-size: .875rem; direction: ltr; }
        .blog-content pre { margin: 2rem 0; overflow-x: auto; border-radius: 1rem; background: #1e293b; padding: 1.25rem; color: #e2e8f0; direction: ltr; text-align: left; }
        .blog-content pre code { background: none; padding: 0; color: inherit; }
        .blog-content img { margin: 2rem 0; border-radius: 1rem; }
        .blog-content hr { margin: 2.5rem 0; border: 0; border-top: 1px solid #e2e8f0; }
    </style>
@endsection
