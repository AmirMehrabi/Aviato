@extends('layouts.admin')

@section('title', 'حساب‌های هتزنر')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">حساب‌های هتزنر</h1>
            <p class="mt-1 text-sm text-slate-500">حساب‌های API برای ارائه موقعیت‌های زیرساخت قابل فروش.</p>
        </div>
        <a href="{{ route('admin.hetzner-accounts.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">New account</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($accounts as $account)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">{{ $account->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">اتصال: {{ \App\Support\AdminUi::status($account->connection_status) }} / همگام‌سازی: {{ \App\Support\AdminUi::status($account->sync_status) }}</p>
                    </div>
                    <span class="rounded-md px-2 py-1 text-xs font-black {{ $account->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-slate-50 p-3"><b>{{ $account->locations_count }}</b><br>Locations</div>
                    <div class="rounded-lg bg-slate-50 p-3"><b>{{ $account->images_count }}</b><br>Images</div>
                    <div class="rounded-lg bg-slate-50 p-3"><b>{{ $account->server_types_count }}</b><br>Types</div>
                </div>
                @if ($account->sync_error)
                    <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $account->sync_error }}</p>
                @endif
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('admin.hetzner-accounts.show', $account) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Open</a>
                    <form method="POST" action="{{ route('admin.hetzner-accounts.sync', $account) }}">@csrf<button class="rounded-lg border border-[#B8D6FF] px-4 py-2 text-sm font-black text-[#0069FF]">همگام‌سازی</button></form>
                    <form method="POST" action="{{ route('admin.hetzner-accounts.test', $account) }}">@csrf<button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">آزمایش اتصال</button></form>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm font-bold text-slate-500">No Hetzner accounts yet.</div>
        @endforelse
    </div>
</div>
@endsection
