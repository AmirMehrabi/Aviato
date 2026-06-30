@extends('layouts.admin')

@section('title', 'حساب جدید هتزنر')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="mb-6 text-2xl font-black">حساب جدید هتزنر</h1>
        <form method="POST" action="{{ route('admin.hetzner-accounts.store') }}">
            @include('admin.hetzner-accounts._form')
        </form>
    </div>
</div>
@endsection
