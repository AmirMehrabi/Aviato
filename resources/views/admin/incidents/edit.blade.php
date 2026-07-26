@extends('layouts.admin')
@section('title', 'مدیریت رخداد')
@section('content')
<div class="mx-auto max-w-5xl p-4 md:p-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <a href="{{ route('admin.incidents.index') }}" class="text-sm font-black text-[#0069FF]">← گزارش رخدادها</a>
            <h1 class="mt-3 text-3xl font-black text-slate-950">{{ $incident->title }}</h1>
        </div>
        @if ($incident->is_published)
            <a target="_blank" href="{{ route('incidents.show', $incident->slug) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700">مشاهده گزارش عمومی ↗</a>
        @endif
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.incidents.update', $incident) }}" class="mt-7">
        @csrf @method('PUT')
        @include('admin.incidents._form')
        <div class="mt-6 flex flex-wrap gap-3">
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره رخداد</button>
        </div>
    </form>

    <section class="mt-12 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-black text-[#0069FF]">گاهشمار رخداد</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">زمان‌بندی</h2>
                <p class="mt-1 text-sm text-slate-500">رویدادهای قابل نمایش برای مشتریان را اضافه کنید.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.incidents.timeline.store', $incident) }}" class="mt-6 grid gap-4 rounded-xl bg-slate-50 p-4 md:grid-cols-2">
            @csrf
            <label>
                <span class="text-xs font-black text-slate-600">زمان</span>
                <input type="datetime-local" name="occurred_at" value="{{ old('occurred_at') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
            </label>
            <label>
                <span class="text-xs font-black text-slate-600">عنوان رویداد</span>
                <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
            </label>
            <label class="md:col-span-2">
                <span class="text-xs font-black text-slate-600">توضیحات</span>
                <textarea name="description" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">{{ old('description') }}</textarea>
            </label>
            <button class="w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-black text-white">افزودن رویداد</button>
        </form>

        <div class="mt-6 space-y-4">
            @forelse ($incident->timelineEvents as $event)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-black text-[#0069FF]" dir="ltr">{{ $event->occurred_at->format('Y/m/d H:i') }}</p>
                            <h3 class="mt-1 font-black text-slate-950">{{ $event->title }}</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $event->description }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.incidents.timeline.destroy', [$incident, $event]) }}" onsubmit="return confirm('این رویداد حذف شود؟')">
                            @csrf @method('DELETE')
                            <button class="text-sm font-black text-rose-600 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">هنوز رویدادی اضافه نشده.</p>
            @endforelse
        </div>
    </section>

    <form method="POST" action="{{ route('admin.incidents.destroy', $incident) }}" class="mt-8" onsubmit="return confirm('این رخداد و تمام رویدادهای زمانی آن حذف شود؟')">
        @csrf @method('DELETE')
        <button class="text-sm font-black text-rose-600 hover:underline">حذف رخداد</button>
    </form>
</div>
@endsection
