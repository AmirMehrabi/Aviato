@extends('layouts.admin')
@section('title', 'کولوکیشن و صف نصب')
@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:px-8">
    @include('admin.colocation._navigation')
    <h1 class="text-2xl font-black text-slate-950">کولوکیشن و صف نصب</h1><p class="mt-3 text-sm leading-7 text-slate-500">پلن‌های اختصاصی را برای مشتری تعریف کنید؛ سفارش پرداخت‌شده را نصب و سپس تاریخ شروع صورتحساب را تعیین کنید.</p>
    <div class="mt-6 grid items-start gap-6 xl:grid-cols-3">
        <section class="colo-card xl:col-span-2"><form class="mb-6 flex flex-wrap items-end gap-3" method="GET"><label class="colo-label flex-1">وضعیت سرویس<select class="colo-input" name="status"><option value="">همه وضعیت‌ها</option>@foreach(\App\Models\ColocationService::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></label><button class="colo-btn">فیلتر</button></form>
            <div class="divide-y divide-slate-100">@forelse($services as $service)<a class="block py-5" href="{{ route('admin.colocation.show', $service->uuid) }}"><div class="flex flex-wrap justify-between gap-3"><strong class="text-blue-700">{{ $service->name }}</strong><span class="text-xs font-bold text-slate-500">{{ $service->label() }}</span></div><p class="mt-2 text-sm text-slate-500">{{ $service->customer->name }} · {{ $service->project->name }} · {{ $service->agreement['rack_units'] }}U</p>@if($service->cancellation_requested_at && !$service->ends_at)<p class="mt-2 text-xs font-bold text-amber-700">درخواست پایان سرویس در انتظار بررسی</p>@endif</a>@empty<p class="py-8 text-center text-sm text-slate-500">سرویسی در این وضعیت وجود ندارد.</p>@endforelse</div>{{ $services->links() }}
        </section>
        <section class="colo-card"><h2 class="font-black">پلن و دسترسی مشتری</h2><form method="GET" class="mt-4 flex gap-2"><input class="colo-input min-w-0" aria-label="نام یا ایمیل مشتری" name="q" value="{{ request('q') }}" placeholder="نام یا ایمیل"><button class="colo-btn mt-2">جستجو</button></form><div class="mt-4 divide-y divide-slate-100">@foreach($customers as $customer)<a class="block py-3 text-sm" href="{{ route('admin.colocation.customer', $customer) }}"><strong class="text-blue-700">{{ $customer->name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $customer->colocation_enabled ? 'خرید فعال' : 'خرید غیرفعال' }}</span></a>@endforeach</div></section>
    </div>
</div>
@endsection
