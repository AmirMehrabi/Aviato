@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('own_messages', '1')
@section('title', 'صورتحساب '.$invoice->number)
@section('header_title', 'صورتحساب کولوکیشن')
@section('header_subtitle', $service->name)
@php($activeNav = 'colocation')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @include('customer.colocation._navigation')
    @if($invoice->status === 'paid')<div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><h2 class="font-black">پرداخت تأیید شد؛ این صورتحساب تسویه شده است</h2><p class="mt-2 text-sm leading-7">{{ ($invoice->meta['initial'] ?? false) && !$service->billing_starts_at ? 'قدم بعدی: تحویل تجهیزات و نصب. پس از آماده شدن سرویس، تاریخ شروع اولین دوره اعلام می‌شود.' : 'رسید پرداخت و بازه سرویس در همین صفحه در دسترس است.' }}</p><a href="{{ route('customer.colocation.show', $service->uuid, false) }}" class="mt-4 inline-block text-sm font-black underline">{{ $service->installed_at ? 'مشاهده سرویس' : 'پیگیری نصب و راهنمای تحویل تجهیزات' }} ←</a></div>@endif
    @php($lastPayment = $invoice->payments->first())
    @if($invoice->status === 'issued' && $lastPayment && in_array($lastPayment->status, ['failed', 'cancelled']))<p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">پرداخت قبلی تأیید نشد. می‌توانید دوباره تلاش کنید. اگر مبلغ از حساب بانکی کم شده، با شناسه پرداخت با پشتیبانی تماس بگیرید.</p>@endif
    <section class="colo-card">
        <div class="flex flex-wrap justify-between gap-4"><div><h2 class="text-xl font-black text-slate-950" dir="ltr">{{ $invoice->number }}</h2><p class="mt-2 text-sm text-slate-500">{{ $service->name }} · {{ $activeProject->name }}</p></div><div class="text-sm leading-7 text-slate-500"><p>صدور: {{ \App\Support\Jalali::format($invoice->issued_at, 'Y/m/d') }}</p><p>{{ $invoice->status === 'paid' ? 'پرداخت: '.\App\Support\Jalali::format($invoice->paid_at) : 'مهلت پرداخت: '.\App\Support\Jalali::format($invoice->due_at, 'Y/m/d') }}</p><strong class="{{ $invoice->status === 'paid' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $invoice->status === 'paid' ? 'پرداخت‌شده' : ($invoice->status === 'void' ? 'لغوشده' : ($invoice->due_at?->isPast() ? 'سررسید گذشته' : 'در انتظار پرداخت')) }}</strong></div></div>
        <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-700">{{ ($invoice->meta['coverage_pending'] ?? false) ? 'این پرداخت، اولین دوره کامل سرویس را پوشش می‌دهد. تاریخ شروع و پایان پس از تکمیل نصب مشخص می‌شود.' : 'بازه این صورتحساب: از '.\App\Support\Jalali::format($invoice->period_start, 'Y/m/d').' تا '.\App\Support\Jalali::format($invoice->period_end, 'Y/m/d').' (ابتدای دوره بعد).' }}</p>
        @include('customer.corporate.billing-recipient', ['recipient' => $invoice->billingRecipient()])
        @if($invoice->meta['traffic'] ?? false)<div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-7 text-blue-900"><p>مصرف خروجی نهایی: {{ number_format($invoice->meta['usage_mb'] / 1000, 3) }} GB · سهمیه این ماه: {{ number_format($invoice->meta['included_mb'] / 1000, 3) }} GB</p><p>منبع و توضیح محاسبه: {{ $invoice->meta['source'] }}</p><p class="text-xs">ترافیک هر ماه جداگانه محاسبه می‌شود، حتی اگر فضای رک را سه‌ماهه یا سالانه پرداخت کرده باشید.</p></div>@endif
        <div class="mt-6 hidden overflow-x-auto sm:block"><table class="w-full min-w-[480px] text-right text-sm"><thead class="border-b border-slate-200 text-xs text-slate-500"><tr><th class="py-3">شرح</th><th>تعداد</th><th>نرخ واحد</th><th class="text-left">مبلغ</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($invoice->items as $item)<tr><td class="py-4 font-bold">{{ $item->label }}</td><td>{{ (float) $item->quantity }} {{ match ($item->unit) { 'GB' => 'GB', 'setup' => 'بار', default => 'دوره' } }}</td><td>{{ $wallets->format((int) $item->unit_price, $invoice->currency) }}</td><td class="text-left font-bold">{{ $wallets->format($item->subtotal, $invoice->currency) }}</td></tr>@endforeach</tbody></table></div>
        <div class="mt-6 divide-y divide-slate-100 sm:hidden">
            @foreach($invoice->items as $item)
                <div class="py-4">
                    <p class="text-sm font-bold leading-7">{{ $item->label }}</p>
                    <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm"><span class="text-slate-500">{{ (float) $item->quantity }} {{ match ($item->unit) { 'GB' => 'GB', 'setup' => 'بار', default => 'دوره' } }}</span><strong>{{ $wallets->format($item->subtotal, $invoice->currency) }}</strong></div>
                    <p class="mt-2 text-xs leading-6 text-slate-500">نرخ واحد: {{ $wallets->format((int) $item->unit_price, $invoice->currency) }}</p>
                </div>
            @endforeach
        </div>
        <dl class="mt-5 space-y-3 border-t border-slate-200 pt-5 text-sm"><div class="flex justify-between"><dt>جمع پیش از مالیات</dt><dd>{{ $wallets->format($invoice->subtotal_amount, $invoice->currency) }}</dd></div><div class="flex justify-between"><dt>مالیات ({{ $invoice->tax_rate_percentage }}٪)</dt><dd>{{ $wallets->format($invoice->tax_amount, $invoice->currency) }}</dd></div><div class="flex justify-between border-t border-slate-100 pt-4 text-lg font-black"><dt>{{ $invoice->status === 'paid' ? 'مبلغ تسویه‌شده' : 'مبلغ صورتحساب' }}</dt><dd>{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</dd></div></dl>
        @foreach($invoice->payments->where('status', 'successful') as $payment)<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm leading-7 text-emerald-900"><p>رسید: <span dir="ltr">{{ $payment->receiptNumber() }}</span> · پیگیری بانک: <span dir="ltr">{{ $payment->provider_reference }}</span></p><p>مبلغ پرداخت: {{ $wallets->format($payment->amount, $payment->currency) }} · {{ \App\Support\Jalali::format($payment->paid_at) }}</p></div>@endforeach
    </section>
    @if($invoice->status === 'issued')
        <section class="colo-card colo-no-print"><h2 class="text-lg font-black">پرداخت صورتحساب</h2><p class="mt-3 text-sm leading-7 text-slate-500">پرداخت مستقیم از طریق درگاه بانکی انجام می‌شود. موجودی کیف پول در این پرداخت نقشی ندارد.</p>
            @if($lastPayment?->status === 'pending')
                <p class="mt-4 text-sm leading-7 text-slate-600">یک پرداخت در جریان دارید. می‌توانید همان تراکنش را ادامه دهید؛ پرداخت جدیدی ایجاد نمی‌شود.</p>
                <a class="colo-btn mt-5" href="{{ route('customer.wallet.payments.gateway.show', $lastPayment, false) }}">ادامه پرداخت در {{ $lastPayment->provider === 'mellat' ? 'بانک ملت' : 'زیبال' }}</a>
            @elseif($gateways)
                <form method="POST" action="{{ route('customer.colocation.invoices.pay', $invoice, false) }}" class="mt-5 space-y-4" x-data="{ paying: false }" @submit="paying = true">@csrf<label for="gateway" class="text-sm font-bold">درگاه بانکی</label><select class="colo-input" name="gateway" id="gateway" required>@foreach($gateways as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><button class="colo-btn w-full sm:w-auto" :disabled="paying" x-text="paying ? 'در حال اتصال…' : 'پرداخت {{ $wallets->format($invoice->total_amount, $invoice->currency) }}'">پرداخت {{ $wallets->format($invoice->total_amount, $invoice->currency) }}</button></form>
            @else<p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm leading-7 text-amber-900">درگاه بانکی فعلاً در دسترس نیست. لطفاً کمی بعد دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.</p>@endif
        </section>
    @endif
    <div class="colo-no-print flex flex-wrap gap-5 text-sm"><a class="colo-link" href="{{ route('customer.colocation.show', $service->uuid, false) }}">بازگشت به سرویس</a><button class="colo-link" type="button" onclick="window.print()">چاپ / ذخیره PDF</button><a class="colo-link" href="{{ route('customer.tickets.create', [], false) }}">پرسش درباره صورتحساب</a></div>
</div>
@endsection
