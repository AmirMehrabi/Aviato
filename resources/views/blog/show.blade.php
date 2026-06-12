@extends('layouts.marketing')

@section('title', $post['title'] . ' | بلاگ آویاتو')
@section('description', $post['excerpt'])

@php
    $activePage = 'blog';
@endphp

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-8 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-sm font-bold text-[#2C67C9] transition hover:text-[#0069FF]">
                <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                بازگشت به بلاگ
            </a>

            <div class="mt-6 flex items-center gap-3 text-sm text-slate-500">
                <span class="rounded-full bg-[#EEF5FF] px-3 py-1 text-xs font-bold text-[#2C67C9]">{{ $post['category'] }}</span>
                <span>{{ $post['date_display'] }}</span>
                <span class="text-slate-300">·</span>
                <span>{{ $post['reading_time'] }} مطالعه</span>
            </div>

            <h1 class="mt-5 text-3xl font-black leading-tight text-slate-950 md:text-5xl">
                {{ $post['title'] }}
            </h1>

            <div class="mt-6 flex items-center gap-3 border-b border-slate-100 pb-6">
                <span class="flex size-10 items-center justify-center rounded-full bg-[#EEF5FF] text-sm font-bold text-[#2C67C9]">آ</span>
                <div class="text-sm">
                    <span class="font-bold text-slate-950">{{ $post['author'] }}</span>
                    <span class="mr-2 text-slate-400">·</span>
                    <span class="text-slate-500">{{ $post['date_display'] }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <article class="mx-auto max-w-4xl" dir="rtl">
            <div class="blog-content text-base leading-[2] text-slate-700">
                {!! $post['content'] !!}
            </div>
        </article>

        <div class="mx-auto max-w-4xl border-t border-slate-100 pt-10">
            <div class="rounded-[1.75rem] bg-[#EEF5FF] p-8 text-center md:p-12">
                <h2 class="text-2xl font-black text-slate-950 md:text-3xl">سوالی دارید؟</h2>
                <p class="mt-3 text-slate-600">تیم آویاتو آماده پاسخگویی به سوالات فنی شماست.</p>
                <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl bg-[#4C86E8] px-7 py-3.5 text-sm font-bold text-white transition hover:bg-[#3E76D6]">
                        تماس با ما
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-7 py-3.5 text-sm font-bold text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        دیدن پلن‌ها
                    </a>
                </div>
            </div>
        </div>
    </section>

    <style>
        .blog-content h1 { display: none; }
        .blog-content h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .blog-content h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        .blog-content p {
            margin-top: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .blog-content strong {
            font-weight: 700;
            color: #0f172a;
        }
        .blog-content a {
            color: #2C67C9;
            font-weight: 600;
            text-decoration: none;
        }
        .blog-content a:hover {
            text-decoration: underline;
        }
        .blog-content ul,
        .blog-content ol {
            margin-top: 1.25rem;
            margin-bottom: 1.25rem;
            padding-right: 1.5rem;
        }
        .blog-content li {
            margin-top: 0.5rem;
        }
        .blog-content blockquote {
            border-right: 3px solid #2C67C9;
            padding-right: 1rem;
            margin: 1.5rem 0;
            color: #475569;
        }
        .blog-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        .blog-content th,
        .blog-content td {
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            text-align: right;
        }
        .blog-content th {
            background: #F7FBFF;
            font-weight: 700;
            color: #0f172a;
        }
        .blog-content code {
            font-size: 0.875rem;
            background: #f1f5f9;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
        }
        .blog-content pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1.25rem;
            border-radius: 0.75rem;
            overflow-x: auto;
            margin: 1.5rem 0;
        }
        .blog-content pre code {
            background: none;
            padding: 0;
            color: inherit;
        }
        .blog-content img {
            border-radius: 0.75rem;
            margin: 1.5rem 0;
        }
        .blog-content hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 2rem 0;
        }
    </style>
@endsection
