@extends('layouts.admin')
@section('title', 'افزودن کاربر پنل')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10"><div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h1 class="text-2xl font-black">افزودن کاربر پنل</h1><p class="mt-2 text-sm text-slate-500">نقش‌ها ثابت هستند و دسترسی از همین نقش محاسبه می‌شود.</p>@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" action="{{ route('admin.users.store') }}" class="mt-6">@include('admin.users._form')</form></div></div>
@endsection
