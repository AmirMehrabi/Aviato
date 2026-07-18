@extends('layouts.admin')

@section('title', 'تنظیمات')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="w-full">
        <div class="max-w-2xl">
            <p class="text-sm font-black text-[#0069FF]">مرکز تنظیمات</p>
            <h1 class="mt-2 text-2xl font-black text-slate-950">تنظیمات سیستم</h1>
            <p class="mt-2 text-sm leading-7 text-slate-500">برای پیدا کردن تنظیمات موردنظر، یکی از بخش‌های زیر را انتخاب کنید. هر بخش صفحه و فرم ذخیره‌سازی مستقل خودش را دارد.</p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($sections as $key => $section)
                <a href="{{ route('admin.settings.section', $key) }}" class="group flex min-h-44 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#9CC3FF] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#0069FF] focus:ring-offset-2">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl bg-[#EBF3FF] text-[#0069FF]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 12h18"/></svg>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500">{{ $section['label'] }}</span>
                    </div>
                    <h2 class="mt-5 text-base font-black text-slate-950 group-hover:text-[#0069FF]">{{ $section['title'] }}</h2>
                    <p class="mt-2 text-xs leading-6 text-slate-500">{{ $section['description'] }}</p>
                    <span class="mt-auto pt-4 text-xs font-black text-[#0069FF]">مشاهده و ویرایش <span aria-hidden="true">←</span></span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
