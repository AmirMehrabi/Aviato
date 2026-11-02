@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('own_messages', '1')
@section('title', $service->name)
@section('header_title', $service->name)
@section('header_subtitle', 'پیگیری نصب، جزئیات سرویس و پرداخت‌ها')
@php($activeNav = 'colocation')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @include('customer.colocation._navigation')
    <div class="colo-card">
        <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-black text-slate-950">{{ $service->agreement['name'] }}</h2><p class="mt-2 text-xs text-slate-500" dir="ltr">CO-{{ $service->id }}</p></div><span class="rounded-full bg-blue-50 px-4 py-2 text-sm font-bold text-blue-800">{{ $service->label() }}</span></div>
        <div class="mt-6 grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-3">
            <div><p class="text-xs text-slate-500">هزینه ثابت ({{ \App\Models\ColocationPlan::INTERVALS[$service->billing_interval] }})</p><p class="mt-2 font-black">{{ $wallets->format($service->agreement['recurring_price'], $service->agreement['currency']) }}</p><p class="mt-1 text-xs text-slate-500">پیش از مالیات {{ $service->agreement['tax_rate'] }}٪</p></div>
            <div><p class="text-xs text-slate-500">شروع صورتحساب</p><p class="mt-2 font-bold">{{ $service->billing_starts_at ? \App\Support\Jalali::format($service->billing_starts_at, 'Y/m/d') : 'پس از تکمیل نصب مشخص می‌شود' }}</p></div>
            <div><p class="text-xs text-slate-500">پرداخت‌شده تا</p><p class="mt-2 font-bold">{{ $service->paid_through ? \App\Support\Jalali::format($service->paid_through, 'Y/m/d').' (ابتدای دوره بعد)' : ($service->paid_at ? 'اولین دوره؛ تاریخ هنوز مشخص نیست' : 'در انتظار پرداخت') }}</p></div>
        </div>
        @if($service->billing_starts_at && !$service->ends_at)
            <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">صورتحساب تمدید بعدی: {{ \App\Support\Jalali::format($service->boundary($service->next_period)->subDays(14), 'Y/m/d') }} · سررسید دوره بعد: {{ \App\Support\Jalali::format($service->boundary($service->next_period), 'Y/m/d') }}. پرداخت از طریق صورتحساب است.</p>
        @elseif(!$service->billing_starts_at && $service->paid_at)
            <p class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm leading-7 text-emerald-900">اولین دوره پرداخت شده است. تیم ما نصب را پیگیری می‌کند و تاریخ شروع صورتحساب را به شما اطلاع می‌دهد.</p>
        @endif
        @if($service->ends_at)<p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">تاریخ پایان تأییدشده: {{ \App\Support\Jalali::format($service->ends_at, 'Y/m/d') }}. پس از این تاریخ دوره جدیدی صادر نمی‌شود. تحویل تجهیزات را با پشتیبانی هماهنگ کنید.</p>@endif
    </div>
    @if(!$service->installed_at && $service->paid_at)
        <section class="colo-card"><h2 class="text-lg font-black">مراحل نصب شما</h2><ol class="mt-5 grid gap-3 sm:grid-cols-5">@foreach(['awaiting_installation' => 'پرداخت تأیید شد', 'received' => 'دریافت تجهیزات', 'installing' => 'نصب و اتصال', 'testing' => 'آزمایش', 'active' => 'آماده‌سازی نهایی'] as $step => $label)<li class="rounded-xl p-4 text-sm {{ array_search($step, array_keys(['awaiting_installation'=>1,'received'=>1,'installing'=>1,'testing'=>1,'active'=>1])) <= array_search($service->status, ['awaiting_installation','received','installing','testing','active']) ? 'bg-blue-50 font-bold text-blue-800' : 'bg-slate-50 text-slate-500' }}">{{ $loop->iteration }}. {{ $label }}</li>@endforeach</ol>
            @if($service->agreement['delivery_instructions'])<h3 class="mt-6 text-sm font-bold">راهنمای تحویل تجهیزات</h3><p class="mt-3 whitespace-pre-line text-sm leading-8 text-slate-600">{{ $service->agreement['delivery_instructions'] }}</p>@endif
        </section>
    @endif
    <section class="colo-card"><h2 class="mb-5 text-lg font-black">صورتحساب‌ها و پرداخت‌ها</h2>@foreach($service->invoices as $invoice)@include('customer.colocation._invoice-row')@endforeach</section>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="colo-card"><h2 class="mb-5 text-lg font-black">مشخصات و ترافیک</h2>@include('customer.colocation._specifications', ['spec' => $service->agreement])
            @if(in_array($service->agreement['traffic_policy'], ['included', 'metered']))<p class="mt-4 text-xs leading-7 text-slate-500">مصرف نهایی هر ماه در صورتحساب همان دوره ثبت می‌شود. نبود صورتحساب به معنی مصرف صفر نیست؛ آمار لحظه‌ای در این بخش ارائه نمی‌شود.</p>@endif
            <h3 class="mt-6 text-sm font-bold">تجهیزات ثبت‌شده</h3><p class="mt-3 whitespace-pre-line text-sm leading-8 text-slate-600">{{ $service->equipment ?: 'پس از دریافت تجهیزات، مشخصات و شماره سریال‌ها اینجا ثبت می‌شود.' }}</p>
        </section>
        <section class="colo-card"><h2 class="text-lg font-black">آخرین پیگیری‌ها</h2><ol class="mt-5 space-y-5">@foreach($service->events as $event)<li class="border-r-2 border-blue-100 pr-4"><p class="text-sm leading-7 text-slate-700">{{ $event->message }}</p><time class="mt-1 block text-xs text-slate-400">{{ \App\Support\Jalali::format($event->created_at) }}</time></li>@endforeach</ol></section>
    </div>
    <details class="colo-card"><summary class="cursor-pointer font-bold text-slate-900">توافق و شرایط پایان سرویس</summary><p class="mt-4 whitespace-pre-line text-sm leading-8 text-slate-600">{{ $service->agreement['terms'] }}</p><p class="mt-3 text-xs leading-7 text-slate-500">توافق پذیرفته‌شده در {{ \App\Support\Jalali::format($service->accepted_at) }}. دوره‌ها بر اساس سالگرد میلادی شروع سرویس و به وقت تهران محاسبه می‌شوند.</p>
        @if($service->cancellation_requested_at)<p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">درخواست پایان سرویس شما ثبت شده و در حال پیگیری است. {{ $service->ends_at ? 'تاریخ پایان در بالای صفحه مشخص شده است.' : 'تاریخ قطعی و تسویه نهایی را پشتیبانی اعلام می‌کند.' }}</p>
        @elseif($service->status !== 'cancelled')<form class="mt-6 space-y-4 border-t border-slate-100 pt-5" method="POST" action="{{ route('customer.colocation.cancel', $service->uuid, false) }}">@csrf<label class="block text-sm font-bold" for="cancellation-reason">درخواست پایان سرویس</label><textarea id="cancellation-reason" class="colo-input" name="reason" required maxlength="2000" placeholder="دلیل و زمان پیشنهادی جمع‌آوری تجهیزات" rows="3"></textarea><p class="text-xs leading-7 text-slate-500">این درخواست فوراً برق یا شبکه را قطع نمی‌کند. تاریخ پایان، هزینه‌های باقی‌مانده و تحویل تجهیزات طبق قرارداد هماهنگ می‌شود.</p><button class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">ارسال درخواست پایان سرویس</button></form>@endif
    </details>
    <a href="{{ route('customer.tickets.create', [], false) }}" class="colo-link colo-no-print inline-block">برای سرویس خود کمک می‌خواهید؟ ارتباط با پشتیبانی ←</a>
</div>
@endsection
