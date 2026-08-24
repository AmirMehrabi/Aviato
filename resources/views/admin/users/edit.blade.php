@extends('layouts.admin')
@section('title', 'ویرایش کاربر پنل')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10"><div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h1 class="text-2xl font-black">ویرایش {{ $managedUser->name }}</h1>@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="mt-6">@include('admin.users._form')</form></div></div>
@endsection
