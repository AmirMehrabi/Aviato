@extends('layouts.marketing')

@section('title', $service['label'].' | آویاتو')
@section('description', $service['body'])

@php($activePage = 'solutions')

@section('content')
    <section class="bg-[#F5F8FD] px-4 pb-16 pt-32 md:px-8 md:pb-20">
        <div class="mx-auto max-w-7xl">
            <a href="{{ route('solutions') }}" class="text-sm font-bold text-[#0069FF] hover:underline">راهکارهای آویاتو / {{ $service['label'] }}</a>
            <div class="mt-10 grid gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <div>
                    <p class="text-sm font-bold text-[#0069FF]" dir="ltr">{{ $service['eyebrow'] }}</p>
                    <h1 class="mt-4 max-w-2xl text-4xl leading-[1.4] text-slate-950 md:text-5xl">{{ $service['title'] }}</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-9 text-slate-600">{{ $service['body'] }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route($service['action_route']) }}" class="rounded-xl bg-[#0069FF] px-6 py-4 text-sm font-bold text-white hover:bg-[#0050D0]">{{ $service['action'] }} ←</a>
                        <a href="{{ route('contact') }}" class="rounded-xl border border-slate-300 bg-white px-6 py-4 text-sm font-bold text-slate-700 hover:border-blue-400">مشاوره پیش از شروع</a>
                    </div>
                    @if ($service['key'] !== 'cloud')
                        <p class="mt-4 text-xs leading-7 text-slate-500">برای استفاده از سرویس، وارد حساب مشتری شوید یا حساب بسازید.</p>
                    @endif
                </div>
                <aside class="rounded-3xl bg-[#07172D] p-8 text-white">
                    <h2 class="text-xl">{{ $service['label'] }}</h2>
                    <ul class="mt-6 space-y-5">
                        @foreach ($service['points'] as $point)
                            <li class="flex gap-3 text-sm leading-8"><span aria-hidden="true" class="text-blue-300">✓</span>{{ $point }}</li>
                        @endforeach
                    </ul>
                </aside>
            </div>
        </div>
    </section>
    <section class="bg-white px-4 py-16 md:px-8">
        <div class="mx-auto max-w-7xl">
            <h2 class="text-3xl leading-relaxed text-slate-950">مسیر شروع</h2>
            <ol class="mt-8 grid gap-8 md:grid-cols-3">
                @foreach ($service['steps'] as $step)
                    <li class="border-t border-blue-200 pt-5"><span class="text-sm font-bold text-[#0069FF]" aria-hidden="true">0{{ $loop->iteration }}</span><p class="mt-3 leading-8 text-slate-700">{{ $step }}</p></li>
                @endforeach
            </ol>
            <div class="mt-12 rounded-2xl bg-slate-50 p-6 md:p-8">
                <h2 class="text-lg font-bold text-slate-950">پیش از شروع بدانید</h2>
                <p class="mt-3 max-w-4xl text-sm leading-8 text-slate-600">{{ $service['note'] }}</p>
            </div>
            <div class="mt-10 flex flex-wrap gap-5">
                @foreach (config('solutions') as $related)
                    @if ($related['key'] !== $service['key'])
                        <a href="{{ route($related['route']) }}" class="text-sm font-bold text-[#0069FF] hover:underline">{{ $related['label'] }} ←</a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endsection
