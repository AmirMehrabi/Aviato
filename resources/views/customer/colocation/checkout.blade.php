@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('own_messages', '1')
@section('title', 'سفارش هم‌مکانی')
@section('header_title', 'انتخاب دوره و بررسی سفارش')
@section('header_subtitle', 'قبل از پرداخت، مبلغ و شرایط سرویس را بررسی کنید')
@php
    $activeNav = 'colocation';
    $selectedInterval = is_string(old('billing_interval')) && array_key_exists(old('billing_interval'), $plan->intervals()) ? old('billing_interval') : array_key_first($plan->intervals());
@endphp
@section('content')
<div class="mx-auto max-w-5xl">
    @include('customer.colocation._navigation')
    <form method="POST" action="{{ route('customer.colocation.store', $plan->uuid, false) }}" class="grid items-start gap-6 lg:grid-cols-3" x-data="{ interval: @js($selectedInterval), prices: @js(collect($plan->intervals())->mapWithKeys(fn ($label, $key) => [$key => $wallets->format((int) round(($plan->{$key.'_price'} + $plan->setup_fee) * (1 + $plan->tax_rate / 100)), $plan->currency)])) }">
        @csrf
        <input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><input type="hidden" name="checkout_key" value="{{ old('checkout_key', $checkoutKey) }}"><input type="hidden" name="plan_version" value="{{ $planVersion }}">
        <div class="space-y-6 lg:col-span-2">
            <section class="colo-card"><h2 class="mb-5 text-xl font-black text-slate-950">{{ $plan->name }}</h2>@include('customer.colocation._specifications', ['spec' => $plan->toArray()])</section>
            <section class="colo-card"><h2 class="text-lg font-black text-slate-950">نام و دوره سرویس</h2>
                <label class="mt-5 block text-sm font-bold" for="service-name">نام سرویس</label><input id="service-name" class="colo-input" name="name" maxlength="100" required value="{{ old('name', $plan->name) }}" placeholder="مثلاً تجهیزات دفتر مرکزی" autocomplete="off">
                <fieldset class="mt-6"><legend class="mb-3 text-sm font-bold">هر چند وقت پرداخت می‌کنید؟</legend><div class="grid gap-3 sm:grid-cols-3">@foreach($plan->intervals() as $interval => $label)<label class="cursor-pointer rounded-xl border border-slate-200 p-4 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50"><input type="radio" name="billing_interval" value="{{ $interval }}" x-model="interval" @checked($selectedInterval === $interval) required><span class="mr-2 text-sm font-bold">{{ $label }}</span><span class="mt-3 block text-sm font-black">{{ $wallets->format($plan->{$interval.'_price'}, $plan->currency) }}</span><span class="mt-1 block text-xs text-slate-500">مبلغ کامل هر دوره، پیش از مالیات</span></label>@endforeach</div></fieldset>
            </section>
            <section class="colo-card"><h2 class="text-lg font-black text-slate-950">شرایط توافق شما</h2><p class="mt-4 whitespace-pre-line text-sm leading-8 text-slate-600">{{ $plan->terms }}</p>
                <p class="mt-4 text-sm leading-7 text-slate-600">با پرداخت، سفارش نصب ثبت می‌شود. تاریخ شروع سرویس را تیم نصب تعیین و اعلام می‌کند. تمدید با صدور صورتحساب انجام می‌شود؛ برداشت خودکار نداریم. دوره‌ها بر اساس سالگرد میلادی شروع سرویس و به وقت تهران محاسبه و تاریخ‌ها در پنل شمسی نمایش داده می‌شوند.</p>
                <label class="mt-5 flex items-start gap-3 text-sm leading-7"><input class="mt-2 size-4 shrink-0" type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms') && !$errors->has('plan_version'))><span>مشخصات، مبلغ، شرایط تمدید و لغو و نحوه محاسبه ترافیک را خوانده‌ام و می‌پذیرم.</span></label>
            </section>
        </div>
        <aside class="colo-card lg:sticky lg:top-6"><h2 class="text-lg font-black text-slate-950">اولین پرداخت</h2><p class="mt-3 text-sm leading-7 text-slate-500">هزینه کامل اولین دوره انتخاب‌شده به‌علاوه راه‌اندازی و مالیات مشخص‌شده.</p>
            <dl class="mt-5 space-y-4 text-sm"><div class="flex justify-between gap-3"><dt>راه‌اندازی یک‌باره</dt><dd class="font-bold">{{ $wallets->format($plan->setup_fee, $plan->currency) }}</dd></div><div class="flex justify-between"><dt>مالیات</dt><dd>{{ $plan->tax_rate }}٪</dd></div></dl>
            <div class="my-6 border-t border-slate-200 pt-5"><p class="text-sm text-slate-500">مجموع قابل پرداخت</p><p class="mt-2 text-2xl font-black text-slate-950" x-text="prices[interval]">{{ $wallets->format((int) round(($plan->{$selectedInterval.'_price'} + $plan->setup_fee) * (1 + $plan->tax_rate / 100)), $plan->currency) }}</p></div>
            <p class="mb-5 rounded-xl bg-blue-50 p-3 text-xs leading-7 text-blue-900">شروع دوره پس از تکمیل نصب و تعیین تاریخ توسط تیم ماست. پیش از آن هزینه تمدید ایجاد نمی‌شود.</p>
            <button class="colo-btn w-full">ثبت سفارش و مشاهده صورتحساب</button><p class="mt-3 text-xs leading-6 text-slate-500">در صفحه بعد، درگاه را انتخاب و پرداخت را انجام می‌دهید.</p>
        </aside>
    </form>
</div>
@endsection
