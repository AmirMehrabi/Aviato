@extends('layouts.admin')
@section('title', 'IP Pool جدید')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="mb-6 text-2xl font-black">IP Pool جدید</h1>
        <form method="POST" action="{{ route('admin.ip-pools.store') }}">
            @include('admin.ip-pools._form')
        </form>
    </div>
</div>
@endsection
