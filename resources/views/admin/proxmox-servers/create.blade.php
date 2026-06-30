@extends('layouts.admin')

@section('title', 'افزودن Proxmox')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold text-[#0069FF]">اتصال جدید</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">افزودن سرور / کلاستر Proxmox</h1>
            <p class="mt-2 text-sm text-slate-500">اطلاعات اتصال را ذخیره کنید؛ sync می‌تواند همین حالا یا بعداً انجام شود.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.proxmox-servers.store') }}">
        @include('admin.proxmox-servers._form')
    </form>
</div>
@endsection
