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
<form method="POST" action="{{ route('customer.tickets.store', [], false) }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
    @csrf
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

        <label class="mt-4 block">
            <span class="text-sm font-black text-slate-700">موضوع</span>
            <input name="subject" value="{{ old('subject') }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </label>

        <label class="mt-4 block">
            <span class="text-sm font-black text-slate-700">توضیحات</span>
            <textarea name="body" rows="12" data-ticket-editor class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">{{ old('body') }}</textarea>
        </label>

        <label class="mt-4 block">
            <span class="text-sm font-black text-slate-700">پیوست‌ها</span>
            <input type="file" name="attachments[]" multiple class="mt-2 w-full rounded-xl border border-dashed border-slate-300 px-4 py-4 text-sm font-bold">
        </label>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="font-black text-slate-950">سرویس مرتبط</h2>
            <p class="mt-2 text-xs leading-6 text-slate-500">انتخاب سرویس اختیاری است، اما به تیم پشتیبانی کمک می‌کند سریع‌تر بررسی کند.</p>
            <select name="virtual_machine_id" class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
                <option value="">بدون سرویس</option>
                @foreach($virtualMachines as $vm)
                    <option value="{{ $vm->id }}" @selected(old('virtual_machine_id') == $vm->id) dir="ltr">{{ $vm->name }} - {{ $vm->ip_address ?: 'no-ip' }}</option>
                @endforeach
            </select>
        </section>
        <button class="w-full rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت تیکت</button>
        <a href="{{ route('customer.tickets.index', [], false) }}" class="block rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-black text-slate-700">بازگشت</a>
    </aside>
</form>
@endsection
