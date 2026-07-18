@extends('layouts.admin')

@section('title', $sectionMeta['title'])

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="w-full">
        <a href="{{ route('admin.settings.edit') }}" class="inline-flex items-center gap-2 text-sm font-black text-[#0069FF]">→ بازگشت به تنظیمات</a>
        <div class="mt-5 w-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
            <p class="text-sm font-black text-[#0069FF]">{{ $sectionMeta['label'] }}</p>
            <h1 class="mt-2 text-2xl font-black text-slate-950">{{ $sectionMeta['title'] }}</h1>
            <p class="mt-2 text-sm leading-7 text-slate-500">{{ $sectionMeta['description'] }}</p>

            <form method="POST" action="{{ route('admin.settings.section.update', $section) }}" class="mt-7 space-y-6">
                @csrf @method('PATCH')
                @include('admin.settings.sections.'.$section)
                <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                    <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white hover:bg-[#0050D0]">ذخیره تنظیمات</button>
                    <a href="{{ route('admin.settings.edit') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-600">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
