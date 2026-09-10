@extends('layouts.blog')

@section('title', 'بلاگ آویاتو | نوشته‌ها و تجربه‌ها')
@section('description', 'درباره ساختن آویاتو، تصمیم‌های فنی و چیزهایی که در مسیر یاد می‌گیریم.')

@section('content')
    <section class="journal-intro" aria-labelledby="journal-title">
        <p class="journal-eyebrow">از پشت صحنهٔ آویاتو</p>
        <h1 id="journal-title">نوشته‌های آویاتو<span class="journal-dot">.</span></h1>
        <p class="journal-deck">درباره ساختن آویاتو، تصمیم‌های فنی و چیزهایی که در مسیر یاد می‌گیریم.</p>
    </section>

    @if (count($categories) > 1)
        <nav class="journal-topics" aria-label="موضوع نوشته‌ها">
            <a href="{{ route('blog') }}" @if ($selectedCategory === '') aria-current="page" @endif>همه</a>
            @foreach ($categories as $category)
                <a href="{{ route('blog', ['category' => $category]) }}" @if ($selectedCategory === $category) aria-current="page" @endif>{{ $category }}</a>
            @endforeach
        </nav>
    @endif

    <section class="journal-posts" aria-label="نوشته‌ها به ترتیب انتشار">
        @forelse ($posts as $post)
            <article class="journal-entry">
                <p class="journal-meta">{{ $post['date_display'] }} <span aria-hidden="true">/</span> {{ $post['author'] }}</p>
                <h2><a href="{{ route('blog.show', $post['slug']) }}">{{ $post['title'] }}</a></h2>
                <p class="journal-excerpt">{{ $post['excerpt'] }}</p>
                <a class="journal-read" href="{{ route('blog.show', $post['slug']) }}" aria-label="ادامه مطلب: {{ $post['title'] }}">ادامه مطلب <span aria-hidden="true">←</span></a>
            </article>
        @empty
            <div class="journal-empty">
                <h2>هنوز نوشته‌ای در این بخش نیست.</h2>
                <p>نوشته‌های تازه را همین‌جا منتشر می‌کنیم.</p>
                @if ($selectedCategory !== '')
                    <a href="{{ route('blog') }}">دیدن همه نوشته‌ها</a>
                @endif
            </div>
        @endforelse
    </section>
@endsection
