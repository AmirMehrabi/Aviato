@extends('layouts.marketing')

@section('title', $incident->title . ' | گزارش رخدادها | آویاتو')
@section('description', $incident->meta_description ?: $incident->impact_summary)
@section('og_title', $incident->title . ' | آویاتو - گزارش رخداد')
@section('og_description', $incident->meta_description ?: $incident->impact_summary)
@section('og_type', 'article')

@php
    $activePage = 'incidents';
    $sections = [
        ['خلاصه', $incident->summary],
        ['علت ریشه‌ای', $incident->root_cause],
        ['تأثیر بر مشتریان', $incident->customer_impact],
        ['رفع مشکل', $incident->resolution],
        ['اقدامات بعدی', $incident->next_steps],
    ];
@endphp

@section('content')
    <main class="bg-white px-4 pb-24 pt-28 md:px-8 lg:px-10">
        <div class="mx-auto max-w-6xl">
            <a href="{{ route('incidents.index') }}" class="inline-flex items-center gap-2 text-sm font-black text-[#0069FF] hover:underline"><span aria-hidden="true">→</span> همه گزارش‌ها</a>
            <header class="mt-9 max-w-4xl">
                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500"><span class="rounded-full px-3 py-1 text-xs font-black ring-1 {{ $incident->statusCssClass() }}">{{ $incident->statusLabel() }}</span><span>{{ $incident->affected_service }}</span></div>
                <h1 class="mt-5 text-4xl font-black tracking-tight text-slate-950 md:text-6xl">{{ $incident->title }}</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">{{ $incident->impact_summary }}</p>
                <div class="mt-7 flex flex-wrap gap-x-6 gap-y-2 border-y border-slate-200 py-5 text-sm text-slate-500">
                    <span>شروع {{ jdf($incident->started_at) }}</span>
                    @if ($incident->ended_at)<span>پایان {{ jdf($incident->ended_at) }}</span>@endif
                    @if ($incident->duration_minutes !== null)<span>مدت {{ formatMinutesFa($incident->duration_minutes) }}</span>@endif
                </div>
            </header>

            <div class="mt-14 grid gap-12 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                <article class="min-w-0 space-y-12">
                    @foreach ($sections as [$heading, $body])
                        @if (filled($body))
                            <section>
                                <h2 class="text-2xl font-black text-slate-950">{{ $heading }}</h2>
                                <div class="mt-4 whitespace-pre-line text-base leading-8 text-slate-700">{{ $body }}</div>
                            </section>
                        @endif
                    @endforeach

                    @if ($incident->timelineEvents->isNotEmpty())
                        <section>
                            <h2 class="text-2xl font-black text-slate-950">گاهشمار</h2>
                            <div class="relative mt-6 space-y-7 border-r-2 border-[#CFE2FF] pr-7">
                                @foreach ($incident->timelineEvents as $event)
                                    <div class="relative">
                                        <span class="absolute -right-[2.15rem] top-1.5 size-3 rounded-full bg-[#0069FF] ring-4 ring-[#EBF3FF]"></span>
                                        <p class="text-sm font-black text-[#2C67C9]">{{ jdf($event->occurred_at) }}</p>
                                        <h3 class="mt-1 text-lg font-black text-slate-950">{{ $event->title }}</h3>
                                        <p class="mt-2 leading-7 text-slate-600">{{ $event->description }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if (filled($incident->final_status))
                        <section class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-6">
                            <h2 class="text-2xl font-black text-emerald-950">وضعیت نهایی</h2>
                            <p class="mt-3 leading-7 text-emerald-900">{{ $incident->final_status }}</p>
                        </section>
                    @endif
                </article>

                <aside class="rounded-[1.5rem] border border-slate-200 bg-[#F7FBFF] p-6 lg:sticky lg:top-24">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-[#0069FF]">جزئیات رخداد</p>
                    <dl class="mt-5 space-y-5 text-sm">
                        <div><dt class="font-bold text-slate-500">سرویس</dt><dd class="mt-1 font-black text-slate-950">{{ $incident->affected_service }}</dd></div>
                        <div><dt class="font-bold text-slate-500">وضعیت</dt><dd class="mt-1 font-black text-slate-950">{{ $incident->statusLabel() }}</dd></div>
                        <div><dt class="font-bold text-slate-500">مدت</dt><dd class="mt-1 font-black text-slate-950">{{ formatMinutesFa($incident->duration_minutes ?? 0) }}</dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </main>
@endsection
