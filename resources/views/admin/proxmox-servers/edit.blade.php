@extends('layouts.admin')

@section('title', 'ویرایش Proxmox')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold text-[#105D52]">Desired State</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">ویرایش {{ $server->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">اگر endpoint آفلاین باشد، تغییرات ذخیره و به عنوان pending sync نگهداری می‌شود.</p>
        </div>
        <a href="{{ route('admin.proxmox-servers.show', $server) }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm hover:bg-slate-50">نمایش</a>
    </div>

    <form method="POST" action="{{ route('admin.proxmox-servers.update', $server) }}">
        @method('PUT')
        @include('admin.proxmox-servers._form')
    </form>
</div>
@endsection
