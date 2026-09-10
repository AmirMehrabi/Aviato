@extends('layouts.blog')

@section('title', $post['title'] . ' | بلاگ آویاتو')
@section('description', $post['excerpt'])
@section('og_type', 'article')

@section('content')
    <article class="journal-article">
        <header class="journal-article-header">
            <a class="journal-back" href="{{ route('blog') }}">→ همه نوشته‌ها</a>
            <p class="journal-eyebrow">{{ $post['category'] }}</p>
            <h1>{{ $post['title'] }}</h1>
            <p class="journal-meta">{{ $post['author'] }} <span aria-hidden="true">/</span> {{ $post['date_display'] }}@if ($post['reading_time']) <span aria-hidden="true">/</span> {{ $post['reading_time'] }} مطالعه@endif</p>
            @if ($post['updated_date'])
                <p class="journal-meta">به‌روزرسانی: {{ $post['updated_date'] }}</p>
            @endif
        </header>

        @if ($post['cover_image'])
            <img class="journal-cover" src="{{ asset($post['cover_image']) }}" alt="" fetchpriority="high">
        @endif

        @if (count($post['toc']) >= 3)
            <details class="journal-contents">
                <summary>در این مقاله</summary>
                <nav aria-label="فهرست مقاله">
                    @foreach ($post['toc'] as $heading)
                        <a href="#{{ $heading['id'] }}" @class(['journal-subheading' => $heading['level'] === 3])>{{ $heading['label'] }}</a>
                    @endforeach
                </nav>
            </details>
        @endif

        <div class="journal-prose" dir="rtl">
            {!! $post['content'] !!}
        </div>

        <footer class="journal-article-footer">
            <p>نوشتهٔ {{ $post['author'] }}</p>
            <div x-data="{ message: '' }">
                <button type="button" class="journal-copy" @click="navigator.clipboard ? navigator.clipboard.writeText(@js(route('blog.show', $post['slug']))).then(() => message = 'لینک کپی شد').catch(() => message = 'لینک را از نوار آدرس مرورگر کپی کنید.') : message = 'لینک را از نوار آدرس مرورگر کپی کنید.'">کپی لینک مقاله <span aria-hidden="true">↗︎</span></button>
                <span class="journal-copy-status" role="status" x-text="message"></span>
            </div>
        </footer>
    </article>

    @if (count($relatedPosts) > 0)
        <aside class="journal-related" aria-labelledby="related-title">
            <h2 id="related-title">برای ادامهٔ خواندن</h2>
            @foreach ($relatedPosts as $relatedPost)
                <a href="{{ route('blog.show', $relatedPost['slug']) }}">{{ $relatedPost['title'] }} <span aria-hidden="true">←</span></a>
            @endforeach
        </aside>
    @endif
@endsection
