@extends('layouts.admin')

@section('title', 'ویرایش مشتری')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold text-[#0069FF]">Customer Profile</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">ویرایش {{ $customer->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">اطلاعات تماس، رمز عبور و وضعیت تعلیق را مدیریت کنید.</p>
        </div>
        <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm hover:bg-slate-50">نمایش</a>
    </div>

    <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
        @method('PUT')
        @include('admin.customers._form')
    </form>
</div>
@endsection
