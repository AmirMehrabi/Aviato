@extends('layouts.admin')
@section('title', 'ویرایش Cloud Image')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="mb-6 text-2xl font-black">ویرایش {{ $image->name }}</h1>

@adminRoute('admin.cloud-images.update')
<form method="POST" action="{{ route('admin.cloud-images.update', $image) }}">
            @method('PUT')
            @include('admin.cloud-images._form')
        </form>
@endadminRoute

    </div>
</div>
@endsection
