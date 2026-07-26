@extends('layouts.admin')
@section('title', 'رخداد جدید')
@section('content')
<div class="mx-auto max-w-5xl p-4 md:p-6">
    <a href="{{ route('admin.incidents.index') }}" class="text-sm font-black text-[#0069FF]">← گزارش رخدادها</a>
    <h1 class="mt-5 text-3xl font-black text-slate-950">رخداد جدید</h1>
    <form method="POST" action="{{ route('admin.incidents.store') }}" class="mt-7">
        @csrf
        @include('admin.incidents._form')
        <div class="mt-6 flex gap-3">
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ایجاد رخداد</button>
            <a href="{{ route('admin.incidents.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">لغو</a>
        </div>
    </form>
</div>
@endsection
