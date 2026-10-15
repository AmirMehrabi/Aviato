@extends('customer.layout')

@section('title', 'تیکت جدید')
@section('header_title', 'ثبت تیکت جدید')
@section('header_subtitle', 'موضوع، سرویس مرتبط و توضیح کامل مشکل را وارد کنید')
@section('breadcrumbs')
    <a href="{{ route('customer.tickets.index', [], false) }}" class="transition hover:text-[#0069FF]">تیکت‌ها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
    <span class="truncate text-slate-700">جدید</span>
@endsection
@php($activeNav = 'tickets')

@section('content')
<form method="POST" action="{{ route('customer.tickets.store', [], false) }}" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-5">
    @csrf
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-800" role="alert">
            <p class="font-bold">تیکت ثبت نشد. موارد زیر را بررسی کنید:</p>
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-black text-slate-700">دسته‌بندی</span>
                <select name="ticket_category_id" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('ticket_category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-black text-slate-700">اولویت</span>
                <select name="priority" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
                    @foreach($priorities as $key => $label)
                        <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-4">
            @include('tickets.partials.virtual-machine-select', ['dynamic' => false, 'hasCustomer' => true])
        </div>
        <label class="mt-4 block">
            <span class="text-sm font-black text-slate-700">موضوع</span>
            <input name="subject" value="{{ old('subject') }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </label>

        <label class="mt-4 block">
            <span class="text-sm font-black text-slate-700">توضیحات</span>
            <textarea name="body" rows="12" data-ticket-editor class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">{{ old('body') }}</textarea>
        </label>

        <label class="mt-4 block text-xs font-bold text-slate-500">پیوست (اختیاری) · حداکثر ۵ فایل، هر فایل ۲۰ مگابایت
            <input type="file" name="attachments[]" multiple data-ticket-attachments class="mt-2 block w-full text-xs file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
        </label>
    </section>

    <div class="flex flex-wrap items-center gap-3">

        <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت تیکت</button>
        <a href="{{ route('customer.tickets.index', [], false) }}" class="block rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-black text-slate-700">بازگشت</a>
    </div>
</form>
@endsection
