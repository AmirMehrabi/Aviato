@extends('layouts.admin')

@section('title', 'تیکت جدید')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <form method="POST" action="{{ route('admin.tickets.store') }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-2xl font-black text-slate-950">ایجاد تیکت برای مشتری</h1>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="block"><span class="text-sm font-black text-slate-700">مشتری</span><select name="customer_id" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $selectedCustomer?->id) == $customer->id)>{{ $customer->name }} - {{ $customer->email ?: $customer->phone }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">دسته‌بندی</span><select name="ticket_category_id" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('ticket_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">اولویت</span><select name="priority" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">@foreach($priorities as $key => $label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-black text-slate-700">مسئول</span><select name="assigned_user_id" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"><option value="">تخصیص خودکار</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('assigned_user_id') == $agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
            </div>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">سرویس اختیاری</span><select name="virtual_machine_id" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"><option value="">بدون سرویس</option>@foreach(($selectedCustomer?->virtualMachines ?? collect()) as $vm)<option value="{{ $vm->id }}" @selected(old('virtual_machine_id') == $vm->id) dir="ltr">{{ $vm->display_name }}</option>@endforeach</select><span class="mt-2 block text-xs font-bold text-slate-500">برای انتخاب VM، از صفحه مشتری با پارامتر customer_id وارد شوید یا بعد از ساخت از داخل تیکت لینک کنید.</span></label>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">موضوع</span><input name="subject" value="{{ old('subject') }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"></label>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">متن</span><textarea name="body" rows="12" data-ticket-editor class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold">{{ old('body') }}</textarea></label>
            <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="mt-4 w-full rounded-lg border border-dashed border-slate-300 px-4 py-4 text-sm font-bold">
        </section>
        <aside class="space-y-3">
            <button class="w-full rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت تیکت</button>
            <a href="{{ route('admin.tickets.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700">بازگشت</a>
        </aside>
    </form>
</div>
@endsection
