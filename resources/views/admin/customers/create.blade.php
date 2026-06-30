@extends('layouts.admin')

@section('title', 'افزودن مشتری')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6">
        <p class="text-sm font-bold text-[#0069FF]">مشتری جدید</p>
        <h1 class="mt-1 text-2xl font-black text-slate-950">افزودن مشتری</h1>
        <p class="mt-2 text-sm text-slate-500">اطلاعات پایه و وضعیت دسترسی مشتری را ثبت کنید.</p>
    </div>

    <form method="POST" action="{{ route('admin.customers.store') }}">
        @include('admin.customers._form')
    </form>
</div>
@endsection
