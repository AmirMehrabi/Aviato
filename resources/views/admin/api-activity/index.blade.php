@extends('layouts.admin')

@section('title', 'فعالیت API')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6">
        <p class="text-xs font-black text-[#0069FF]">API OPERATIONS</p>
        <h1 class="mt-2 text-2xl font-black text-slate-950">فعالیت API</h1>
        <p class="mt-2 text-sm text-slate-500">درخواست‌های موفق و ناموفق مشتریان، بدون ذخیره کلیدهای محرمانه.</p>
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3">
        <input name="customer_id" value="{{ request('customer_id') }}" placeholder="شناسه مشتری" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" dir="ltr">
        <input name="route" value="{{ request('route') }}" placeholder="مسیر API" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" dir="ltr">
        <div class="flex gap-2"><input name="status_code" value="{{ request('status_code') }}" placeholder="کد وضعیت" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm" dir="ltr"><button class="rounded-xl bg-[#0069FF] px-4 py-2 text-sm font-black text-white">فیلتر</button></div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-right text-sm">
            <thead class="bg-slate-50 text-xs font-black text-slate-500"><tr><th class="px-4 py-3">زمان</th><th class="px-4 py-3">مشتری</th><th class="px-4 py-3">درخواست</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3">زمان پاسخ</th><th class="px-4 py-3">Request ID</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr><td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->created_at?->format('Y/m/d H:i:s') }}</td><td class="px-4 py-3 font-bold">{{ $log->customer?->name ?? 'ناشناس' }}</td><td class="px-4 py-3" dir="ltr">{{ $log->method }} {{ $log->route }}</td><td class="px-4 py-3 font-black {{ $log->status_code < 400 ? 'text-emerald-600' : 'text-red-600' }}">{{ $log->status_code }}</td><td class="px-4 py-3" dir="ltr">{{ $log->duration_ms }} ms</td><td class="px-4 py-3 font-mono text-xs text-slate-500" dir="ltr">{{ $log->request_id }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">هنوز فعالیتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $logs->links() }}</div>
</div>
@endsection
