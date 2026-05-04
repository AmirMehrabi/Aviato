@extends('layouts.admin')
@section('title', 'VM جدید')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10"><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h1 class="mb-6 text-2xl font-black">VM جدید</h1><form method="POST" action="{{ route('admin.virtual-machines.store') }}">@include('admin.virtual-machines._form')</form></div></div>
@endsection
