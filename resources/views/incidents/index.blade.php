@extends('layouts.marketing')

@section('title', 'گزارش رخدادها | آویاتو')
@section('description', 'لیست رخدادهای جاری و گذشته آویاتو، تأثیر بر سرویس، جزئیات زمانی و اقدامات انجام شده.')
@section('og_title', 'گزارش رخدادها | آویاتو')

@php
    $activePage = 'incidents';
@endphp

@section('content')
    <main class="bg-[#F7FBFF] px-4 pb-20 pt-28 md:px-8 lg:px-10">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-3xl">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-[#0069FF]">AVIATO / OPERATIONS</p>
                <h1 class="mt-4 text-4xl font-black tracking-tight text-slate-950 md:text-6xl">گزارش رخدادها</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">فهرست شفاف اختلال‌های سرویس، تأثیر بر سرویس‌ها، جزئیات زمانی و اقدامات بهبود.</p>
            </div>

            <div class="mt-12 grid gap-4">
                @forelse ($incidents as $incident)
                    <a href="{{ route('incidents.show', $incident->slug) }}" class="group rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-[#9CC3FF] hover:shadow-lg md:p-8">
                        <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                    <span class="rounded-full px-3 py-1 text-xs font-black ring-1 {{ $incident->statusCssClass() }}">{{ $incident->statusLabel() }}</span>
                                    <span>{{ $incident->affected_service }}</span>
                                </div>
                                <h2 class="mt-4 text-2xl font-black text-slate-950 transition group-hover:text-[#0069FF]">{{ $incident->title }}</h2>
                                <p class="mt-3 max-w-3xl leading-7 text-slate-600">{{ $incident->impact_summary }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-black text-[#0069FF]">مشاهده گزارش <span aria-hidden="true">←</span></span>
                        </div>
                        <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 pt-5 text-sm text-slate-500">
                            <span>شروع {{ jdf($incident->started_at) }}</span>
                            @if ($incident->ended_at)<span>پایان {{ jdf($incident->ended_at) }}</span>@endif
                            @if ($incident->duration_minutes !== null)<span>مدت {{ formatMinutesFa($incident->duration_minutes) }}</span>@endif
                        </div>
                    </a>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-white p-12 text-center text-slate-600">هنوز گزارش رخدادی منتشر نشده است.</div>
                @endforelse
            </div>
            <div class="mt-8">{{ $incidents->links() }}</div>
        </div>
    </main>
@endsection
