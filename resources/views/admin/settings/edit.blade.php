@extends('layouts.admin')

@section('title', 'تنظیمات')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black">تنظیمات سیستم</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">واحد پولی برای نمایش قیمت‌ها، کیف پول و صورتحساب‌ها استفاده می‌شود. تغییر آن تبدیل ارزی انجام نمی‌دهد؛ فقط واحد نمایش و ثبت‌های بعدی را مشخص می‌کند.</p>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-5">
            @csrf @method('PATCH')
            <x-form.select name="currency" label="واحد پولی Billing" :selected="$currency" :options="$currencies" />
            <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره تنظیمات</button>
        </form>
    </div>
</div>
@endsection
