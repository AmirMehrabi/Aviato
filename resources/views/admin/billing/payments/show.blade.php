@extends('layouts.admin')
@section('title','جزئیات پرداخت')
@section('content')<div class="px-4 py-6 md:px-8 lg:px-10">
@adminRoute('admin.billing.payments.index')
<a href="{{ route('admin.billing.payments.index') }}" class="text-sm font-black text-blue-600">بازگشت به پرداخت‌ها</a>
@endadminRoute

@if(session('status'))<p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">{{ session('status') }}</p>@endif
@error('hesabro')<p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror
<div class="mt-4 grid gap-5 xl:grid-cols-[1fr_420px]"><section class="rounded-xl border bg-white p-6"><h1 class="text-xl font-black">پرداخت #{{ $payment->id }}</h1><dl class="mt-6 grid gap-4 md:grid-cols-2">@foreach([['مشتری',$payment->customer?->name],['مبلغ',$wallets->format($payment->amount,$payment->currency)],['وضعیت',$payment->status],['درگاه',$payment->provider],['Authority',$payment->authority],['مرجع درگاه',$payment->provider_reference],['ایجاد',\Morilog\Jalali\Jalalian::fromCarbon($payment->created_at)->format('Y/m/d H:i:s')],['پرداخت',$payment->paid_at ? \Morilog\Jalali\Jalalian::fromCarbon($payment->paid_at)->format('Y/m/d H:i:s'):'—']] as [$k,$v])<div class="border-b pb-3"><dt class="text-xs font-bold text-slate-500">{{ $k }}</dt><dd class="mt-1 font-black break-all">{{ $v ?: '—' }}</dd></div>@endforeach</dl>@if($transaction)
@adminRoute('admin.billing.transactions.show')
<a href="{{ route('admin.billing.transactions.show',$transaction) }}" class="mt-6 inline-flex rounded-lg bg-blue-50 px-4 py-2 text-sm font-black text-blue-700">مشاهده تراکنش کیف پول مرتبط</a>
@endadminRoute
@endif
<div class="mt-6 rounded-xl border border-slate-200 p-4"><h2 class="font-black">ثبت در حسابرو</h2><p class="mt-2 text-sm">وضعیت: {{ ['queued' => 'در صف', 'submitted' => 'ثبت شده', 'failed' => 'ناموفق'][$payment->hesabro_status] ?? 'ارسال نشده' }}</p>@if($payment->hesabro_factor_id)<p class="mt-1 text-sm">شناسه فاکتور: {{ $payment->hesabro_factor_id }}</p>@endif @if($payment->hesabro_error)<p class="mt-1 text-sm text-red-700">{{ $payment->hesabro_error }}</p>@endif
@if($payment->isSuccessful() && !in_array($payment->hesabro_status, ['queued', 'submitted'], true))
@adminRoute('admin.billing.payments.hesabro.submit')
<form method="POST" action="{{ route('admin.billing.payments.hesabro.submit', $payment) }}" class="mt-3">@csrf<button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white">ارسال به حسابرو</button></form>
@endadminRoute
@endif</div>
</section><aside class="rounded-xl border bg-slate-950 p-5 text-slate-100"><h2 class="font-black">داده تشخیصی درگاه (Redacted)</h2><pre dir="ltr" class="mt-4 max-h-[560px] overflow-auto whitespace-pre-wrap break-all text-xs leading-6">{{ json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></aside></div></div>@endsection
