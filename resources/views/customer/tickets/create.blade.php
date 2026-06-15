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

        <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-7 text-center transition hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
            <span class="grid size-12 place-items-center rounded-xl bg-white text-[#0069FF] shadow-sm">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.48-8.49" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="mt-3 text-sm font-black text-slate-800">افزودن پیوست</span>
            <span class="mt-1 text-xs font-bold text-slate-500">PDF، تصویر، لاگ، آرشیو و سندهای مرتبط با درخواست پشتیبانی</span>
            <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="sr-only">
        </label>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="font-black text-slate-950">سرویس مرتبط</h2>
            <p class="mt-2 text-xs leading-6 text-slate-500">انتخاب سرویس اختیاری است، اما به تیم پشتیبانی کمک می‌کند سریع‌تر بررسی کند.</p>
            <select name="virtual_machine_id" class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
                <option value="">بدون سرویس</option>
                @foreach($virtualMachines as $vm)
                    <option value="{{ $vm->id }}" @selected(old('virtual_machine_id') == $vm->id) dir="ltr">{{ $vm->display_name }} - {{ $vm->ip_address ?: 'no-ip' }}</option>
                @endforeach
            </select>
        </section>
        <button class="w-full rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت تیکت</button>
        <a href="{{ route('customer.tickets.index', [], false) }}" class="block rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-black text-slate-700">بازگشت</a>
    </aside>
</form>
@endsection
