@extends('layouts.admin')
@section('title', 'هم‌مکانی — '.$customer->name)
@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 md:px-8">
    @include('admin.colocation._navigation')
    <h1 class="text-2xl font-black">هم‌مکانی — {{ $customer->name }}</h1>
    <section class="colo-card mt-6 flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-black">{{ $customer->colocation_enabled ? 'خرید هم‌مکانی برای این مشتری فعال است' : 'خرید هم‌مکانی برای این مشتری غیرفعال است' }}</h2><p class="mt-2 text-sm leading-7 text-slate-500">ابتدا پلن‌های توافق‌شده را اضافه کنید، سپس دسترسی خرید را فعال کنید. فعال‌سازی دسترسی هیچ هزینه‌ای ایجاد نمی‌کند.</p></div>@adminRoute('admin.colocation.access')<form method="POST" action="{{ route('admin.colocation.access', $customer) }}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{ $customer->colocation_enabled ? 0 : 1 }}"><button class="colo-btn">{{ $customer->colocation_enabled ? 'بستن خرید جدید' : 'فعال‌سازی خرید برای مشتری' }}</button></form>@endadminRoute</section>
    @if($services->isNotEmpty())<section class="colo-card mt-6"><h2 class="font-black">سرویس‌های مشتری</h2>@foreach($services as $service)<a class="mt-4 flex flex-wrap justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm" href="{{ route('admin.colocation.show', $service->uuid) }}"><strong class="text-blue-700">{{ $service->name }} · {{ $service->project->name }}</strong><span>{{ $service->label() }}</span></a>@endforeach</section>@endif
    @adminRoute('admin.colocation.plans.store')<section class="colo-card mt-6"><h2 class="text-lg font-black">افزودن پلن اختصاصی</h2>@include('admin.colocation._plan-form')</section>@endadminRoute
    <section class="mt-8 space-y-4"><h2 class="text-lg font-black">پلن‌های تعریف‌شده</h2>@forelse($plans as $plan)<details class="colo-card"><summary class="cursor-pointer font-bold">{{ $plan->name }} · {{ $plan->rack_units }}U · {{ $plan->available ? 'قابل سفارش' : 'غیرقابل سفارش' }}</summary><p class="mt-3 text-sm text-slate-500">تغییر این پلن، قیمت و قرارداد سرویس‌های قبلی را تغییر نمی‌دهد.</p>@adminRoute('admin.colocation.plans.update')@include('admin.colocation._plan-form')@else<p class="mt-3 text-sm">دسترسی ویرایش ندارید.</p>@endadminRoute</details>@empty<p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">هنوز پلنی تعریف نشده است.</p>@endforelse</section>
</div>
@endsection
