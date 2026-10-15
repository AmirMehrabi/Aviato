@extends('layouts.admin')

@section('title', 'تیکت جدید')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">

@adminRoute('admin.tickets.store')
<form method="POST" action="{{ route('admin.tickets.store') }}" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-5">
        @csrf
        @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-800" role="alert">
            <p class="font-bold">تیکت ثبت نشد. موارد زیر را بررسی کنید:</p>
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-2xl font-black text-slate-950">ایجاد تیکت برای مشتری</h1>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="block"><span class="text-sm font-black text-slate-700">مشتری</span><select name="customer_id" data-ticket-customer required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"><option value="">انتخاب مشتری</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $selectedCustomer?->id) == $customer->id)>{{ $customer->name }} - {{ $customer->email ?: $customer->phone }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">دسته‌بندی</span><select name="ticket_category_id" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('ticket_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">اولویت</span><select name="priority" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">@foreach($priorities as $key => $label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">مسئول</span><select name="assigned_user_id" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"><option value="">تخصیص خودکار</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('assigned_user_id') == $agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
            </div>
            @include('tickets.partials.virtual-machine-select', [
                'dynamic' => true,
                'hasCustomer' => $selectedCustomer !== null,
            ])
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">موضوع</span><input name="subject" value="{{ old('subject') }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"></label>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">متن</span><textarea name="body" rows="12" data-ticket-editor class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">{{ old('body') }}</textarea></label>
            <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="mt-4 w-full rounded-lg border border-dashed border-slate-300 px-4 py-4 text-sm font-bold">
        </section>
        <div class="flex flex-wrap items-center gap-3">
            <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت تیکت</button>

@adminRoute('admin.tickets.index')
<a href="{{ route('admin.tickets.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700">بازگشت</a>
@endadminRoute

        </div>
    </form>
@endadminRoute

</div>
@endsection
