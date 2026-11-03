@extends('layouts.admin')

@section('title', 'پرونده مشتری')

@section('content')
@php
    $admin = auth('admin')->user();
    $tabs = ['overview' => 'نمای کلی'];
    if ($admin->allows('virtual-machines.read') || \App\Support\AdminAccess::canVisit($admin, 'admin.colocation.customer')) {
        $tabs['services'] = 'سرویس‌ها';
    }
    if ($admin->allows('billing.read')) {
        $tabs['billing'] = 'مالی';
    }
    $tabs['settings'] = 'اطلاعات و تنظیمات';
    $requestedTab = request()->query('tab', count(array_filter($transactionFilters)) || request()->has('transactions_page') ? 'billing' : 'overview');
    if ($errors->any() && old('type') && isset($tabs['billing'])) {
        $requestedTab = 'billing';
    }
    $activeTab = is_string($requestedTab) && isset($tabs[$requestedTab]) ? $requestedTab : 'overview';
    $restrictedVms = $admin->allows('virtual-machines.read') ? $virtualMachines->filter(fn ($vm) => data_get($vm->wallet_restriction, 'last_error') || data_get($vm->wallet_restriction, 'wallet_stopped') || data_get($vm->wallet_restriction, 'network_frozen_at')) : collect();
@endphp
<div class="mx-auto max-w-screen-2xl px-4 py-6 text-slate-800 md:px-8 lg:px-10">
    @if(session('status'))
        <div role="status" class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">اطلاعات واردشده را بررسی کنید.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <header class="flex flex-col justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5 md:p-6 xl:flex-row xl:items-center">
        <div class="min-w-0">
            <p class="text-xs text-slate-500">پرونده مشتری · شناسه {{ $customer->id }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="break-words text-2xl font-semibold text-slate-950">{{ $customer->name }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $customer->status === 'suspended' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $customer->status === 'suspended' ? 'تعلیق شده' : 'فعال' }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                <span>ایمیل: <bdi class="break-all">{{ $customer->email ?: 'ثبت نشده' }}</bdi></span>
                <span>موبایل: <bdi>{{ $customer->phone ?: 'ثبت نشده' }}</bdi></span>
            </div>
        </div>
        @if($admin->is_active)
            <div class="flex flex-wrap items-center gap-3">
                @adminRoute('admin.customers.edit')
                    <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">ویرایش اطلاعات</a>
                @endadminRoute
                @if(\App\Support\AdminAccess::canVisit($admin, $customer->status === 'suspended' ? 'admin.customers.activate' : 'admin.customers.suspend'))
                <details class="rounded-lg border border-slate-200 px-4 py-3 text-sm">
                    <summary class="cursor-pointer font-semibold">مدیریت وضعیت حساب</summary>
                    <div class="mt-3">@include('admin.customers.partials.account-action')</div>
                </details>
                @endif
            </div>
        @endif
    </header>

    @if($customer->status === 'suspended')
        <aside class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" aria-label="تعلیق حساب">
            <p class="font-semibold">این مشتری تعلیق شده است.</p>
            <p class="mt-1 text-sm leading-7">{{ $customer->suspension_reason ?: 'دلیلی برای تعلیق ثبت نشده است.' }}</p>
            @if($admin->is_active)
                @adminRoute('admin.customers.activate')
                    <form method="POST" action="{{ route('admin.customers.activate', $customer) }}" class="mt-3">@csrf @method('PATCH')<button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold ring-1 ring-red-200">فعال‌سازی حساب</button></form>
                @endadminRoute
            @endif
        </aside>
    @endif
    @adminAbility('billing.read')
        @if($wallet->is_locked)
            <aside class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <p class="font-semibold">کیف پول قفل است.</p>
                <p class="mt-1 text-sm leading-7">{{ $wallet->lock_reason ?: 'دلیلی برای قفل کیف پول ثبت نشده است.' }}</p>
                <a href="{{ route('admin.customers.show', [$customer, 'tab' => 'billing']) }}" class="mt-2 inline-block py-2 text-sm font-semibold underline">بررسی کیف پول</a>
            </aside>
        @endif
    @endadminAbility
    @if($restrictedVms->isNotEmpty())
        <aside class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">{{ $restrictedVms->count() }} سرویس دارای محدودیت یا خطای کیف پول است.</p>
            <a href="{{ route('admin.customers.show', [$customer, 'tab' => 'services']) }}" class="mt-2 inline-block py-2 underline">بررسی سرویس‌ها</a>
        </aside>
    @endif

    <dl class="mt-6 grid gap-4 md:grid-cols-3">
        @adminAbility('virtual-machines.read')
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">ماشین‌های مجازی</dt><dd class="mt-2 text-2xl font-semibold">{{ $virtualMachines->count() }} <span class="text-sm font-normal text-slate-500">سرویس · {{ $virtualMachines->where('status', 'running')->count() }} روشن</span></dd></div>
        @endadminAbility
        @adminAbility('billing.read')
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">موجودی کیف پول</dt><dd class="mt-2 text-2xl font-semibold {{ $financial['balance'] < 0 ? 'text-red-700' : 'text-blue-700' }}">{{ $wallets->format($financial['balance']) }}</dd></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">هزینه تخمینی ماهانه</dt><dd class="mt-2 text-2xl font-semibold">{{ $wallets->format($financial['monthly_spend']) }}</dd></div>
        @endadminAbility
    </dl>

    <nav aria-label="بخش‌های پرونده مشتری" class="mt-7 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.customers.show', [$customer, 'tab' => $key]) }}" @if($activeTab === $key) aria-current="page" @endif class="rounded-lg px-4 py-3 text-sm font-semibold {{ $activeTab === $key ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($activeTab === 'overview')
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @adminAbility('virtual-machines.read')
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-semibold">وضعیت سرویس‌ها</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ $virtualMachines->count() }} ماشین مجازی ثبت شده؛ {{ $virtualMachines->where('status', 'running')->count() }} ماشین روشن است.</p>
                    <a href="{{ route('admin.customers.show', [$customer, 'tab' => 'services']) }}" class="mt-4 inline-block py-2 text-sm font-semibold text-blue-700">مشاهده سرویس‌ها ←</a>
                </section>
            @endadminAbility
            @adminAbility('billing.read')
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-semibold">خلاصه مالی</h2>
                    <dl class="mt-4 space-y-4 text-sm"><div class="flex flex-wrap justify-between gap-2"><dt class="text-slate-500">مصرف تسویه‌نشده</dt><dd class="font-semibold">{{ $wallets->format($financial['unpaid_total']) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-500">وضعیت کیف پول</dt><dd>{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</dd></div></dl>
                    <a href="{{ route('admin.customers.show', [$customer, 'tab' => 'billing']) }}" class="mt-4 inline-block py-2 text-sm font-semibold text-blue-700">مشاهده جزئیات مالی ←</a>
                </section>
            @endadminAbility
        </div>
        @include('admin.customers.partials.payments')
    @elseif($activeTab === 'services')
        @include('admin.customers.partials.services')
    @elseif($activeTab === 'billing')
        @include('admin.customers.partials.wallet')
        @include('admin.customers.partials.payments')
        @include('admin.customers.partials.invoices')
    @else
        @include('admin.customers.partials.identity')
        @if($admin->is_active)
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">اعلان پیامکی</h2>
                <p class="mt-2 text-sm text-slate-600">وضعیت ارسال پیامک: {{ $customer->sms_notifications_enabled ? 'فعال' : 'غیرفعال' }}</p>
                <div class="mt-4">@include('admin.customers.partials.sms')</div>
            </section>
        @endif
        @include('admin.customers.partials.policy')
    @endif
</div>
@endsection
