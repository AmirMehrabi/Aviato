@extends('layouts.marketing')

@section('title', 'بلاگ آویاتو | مقالات زیرساخت ابری و سرور مجازی')
@section('description', 'مقاله‌ها و راهنماهای زیرساخت ابری، سرور مجازی و مدیریت سرویس‌های آنلاین توسط تیم آویاتو.')

@php
    $activePage = 'blog';
@endphp

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-14 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-4xl text-center">
            <p class="text-sm font-bold text-[#2C67C9]">بلاگ آویاتو</p>
            <h1 class="mt-4 text-4xl font-black leading-tight text-slate-950 md:text-5xl">مقاله‌ها و راهنماها</h1>
            <p class="mt-6 text-lg leading-9 text-slate-600">
                مقاله‌هایی درباره زیرساخت ابری، انتخاب سرور مجازی و مدیریت سرویس‌های آنلاین.
            </p>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-4xl">
            @forelse ($posts as $post)
                @php
                    $isFirst = $loop->first;
                @endphp
                <article class="group {{ $isFirst ? '' : 'mt-8 border-t border-slate-100 pt-8' }}">
                    <a href="{{ route('blog.show', $post['slug']) }}" class="block">
                        <div class="flex items-center gap-3 text-sm text-slate-500">
                            <span class="rounded-full bg-[#EEF5FF] px-3 py-1 text-xs font-bold text-[#2C67C9]">{{ $post['category'] }}</span>
                            <span>{{ $post['date_display'] }}</span>
                            <span class="text-slate-300">·</span>
                            <span>{{ $post['reading_time'] }} مطالعه</span>
                        </div>

                        <h2 class="mt-4 text-2xl font-black leading-tight text-slate-950 transition group-hover:text-[#2C67C9] md:text-3xl {{ $isFirst ? 'md:text-4xl' : '' }}">
                            {{ $post['title'] }}
                        </h2>

                        <p class="mt-3 text-base leading-8 text-slate-600 {{ $isFirst ? 'md:text-lg' : '' }}">
                            {{ $post['excerpt'] }}
                        </p>

                        <div class="mt-5 flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center rounded-full bg-[#EEF5FF] text-xs font-bold text-[#2C67C9]">آ</span>
                            <div class="text-sm">
                                <span class="font-bold text-slate-950">{{ $post['author'] }}</span>
                            </div>
                        </div>
                    </a>
                </article>
            @empty
                <div class="rounded-[1.75rem] border border-dashed border-slate-200 bg-[#F7FBFF] p-12 text-center">
                    <h3 class="text-xl font-bold text-slate-950">فعلا مقاله‌ای منتشر نشده است.</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">مقالات جدید به زودی در این صفحه اضافه خواهند شد.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
