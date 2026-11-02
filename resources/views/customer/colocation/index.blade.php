@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('own_messages', '1')
@section('title', 'کولوکیشن')
@section('header_title', 'کولوکیشن')
@section('header_subtitle', 'فضای اختصاصی تجهیزات شما، با پرداخت دوره‌ای روشن')
@php($activeNav = 'colocation')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @include('customer.colocation._navigation')
    @if($services->isNotEmpty())
        <div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-black text-slate-950">سرویس‌های شما</h2>@if($plans->isNotEmpty())<a href="#plans" class="colo-btn">افزودن سرویس</a>@endif</div>
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($services as $service)
                @php($outstanding = $service->invoices->where('status', 'issued')->sum('total_amount'))
                <article class="colo-card">
                    <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="text-lg font-black text-slate-950">{{ $service->name }}</h3><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $service->label() }}</span></div>
                    <p class="mt-3 text-sm text-slate-500">{{ $service->agreement['name'] }} · {{ $service->agreement['rack_units'] }}U · {{ $service->agreement['location'] }}</p>
                    <p class="mt-5 text-lg font-black text-slate-900">{{ $wallets->format($service->agreement['recurring_price'], $service->agreement['currency']) }} <span class="text-xs font-normal text-slate-500">/ {{ \App\Models\ColocationPlan::INTERVALS[$service->billing_interval] }}</span></p>
                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ $service->billing_starts_at ? 'شروع صورتحساب: '.\App\Support\Jalali::format($service->billing_starts_at, 'Y/m/d').' · پرداخت‌شده تا '.\App\Support\Jalali::format($service->paid_through, 'Y/m/d') : ($service->paid_at ? 'اولین دوره پرداخت شده؛ شروع صورتحساب پس از تکمیل نصب مشخص می‌شود.' : 'برای آغاز نصب، صورتحساب اولیه را پرداخت کنید.') }}</p>
                    @if($outstanding)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-900">قابل پرداخت: {{ $wallets->format($outstanding, $service->agreement['currency']) }}</p>@endif
                    @if($service->cancellation_requested_at)<p class="mt-3 text-sm text-amber-800">درخواست پایان سرویس در حال پیگیری است.</p>@endif
                    <a href="{{ route('customer.colocation.show', $service->uuid, false) }}" class="colo-link mt-5 inline-block">{{ $service->paid_at ? 'جزئیات و پیگیری سرویس ←' : 'ادامه سفارش و پرداخت ←' }}</a>
                </article>
            @endforeach
        </div>
    @endif
    <section id="plans" class="scroll-mt-6">
        <h2 class="text-xl font-black text-slate-950">{{ $services->isEmpty() ? 'پلن اختصاصی خود را انتخاب کنید' : 'پلن‌های قابل سفارش شما' }}</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">قیمت‌ها طبق توافق شماست. یک پلن انتخاب کنید، دوره پرداخت را مشخص کنید و هزینه اولین دوره را بپردازید. تیم ما نصب را پیگیری می‌کند.</p>
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            @forelse($plans as $plan)
                <article class="colo-card flex flex-col">
                    <div class="mb-6 flex items-center justify-between gap-4"><h3 class="text-xl font-black text-slate-950">{{ $plan->name }}</h3><span dir="ltr" class="rounded-xl bg-blue-50 px-4 py-2 text-xl font-black text-blue-700">{{ $plan->rack_units }}U</span></div>
                    @include('customer.colocation._specifications', ['spec' => $plan->toArray()])
                    <dl class="my-5 divide-y divide-slate-100">@foreach($plan->intervals() as $interval => $label)<div class="flex flex-wrap justify-between gap-2 py-3 text-sm"><dt>{{ $label }}</dt><dd class="font-black">{{ $wallets->format($plan->{$interval.'_price'}, $plan->currency) }}</dd></div>@endforeach</dl>
                    <p class="mb-5 text-xs leading-6 text-slate-500">{{ $plan->setup_fee ? 'راه‌اندازی یک‌باره: '.$wallets->format($plan->setup_fee, $plan->currency).' · ' : '' }}{{ $plan->tax_rate ? 'مالیات '.$plan->tax_rate.'٪ در مرحله بعد افزوده می‌شود.' : 'بدون مالیات اضافی.' }}</p>
                    <a href="{{ route('customer.colocation.checkout', $plan->uuid, false) }}" class="colo-btn mt-auto">انتخاب پلن و ادامه</a>
                </article>
            @empty
                <div class="colo-card lg:col-span-2"><h3 class="font-bold text-slate-900">در حال حاضر پلن جدیدی برای سفارش ندارید</h3><p class="mt-3 text-sm leading-7 text-slate-500">برای اضافه کردن فضای بیشتر یا تغییر توافق، با پشتیبانی هماهنگ کنید.</p><a href="{{ route('customer.tickets.create', [], false) }}" class="colo-link mt-4 inline-block">ارتباط با پشتیبانی</a></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
