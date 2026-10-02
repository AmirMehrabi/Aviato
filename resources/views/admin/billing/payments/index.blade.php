@extends('layouts.admin')
@section('title','پرداخت‌ها')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
@include('admin.billing._header',['title'=>'پرداخت‌های درگاه','subtitle'=>'تمام تلاش‌های پرداخت، وضعیت نهایی و شناسه‌های تطبیق درگاه.','export'=>'payments'])
@if(session('status'))<p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">{{ session('status') }}</p>@endif
@error('hesabro')<p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror
@section('extra_filters')<select name="provider" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">همه درگاه‌ها</option>@foreach($providers as $provider)<option @selected(request('provider')===$provider)>{{ $provider }}</option>@endforeach</select><select name="date_basis" aria-label="مبنای تاریخ" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="created" @selected(request('date_basis') !== 'paid')>تاریخ ایجاد</option><option value="paid" @selected(request('date_basis') === 'paid')>تاریخ پرداخت موفق</option></select>@endsection
@include('admin.billing._filters',['statusOptions'=>['pending'=>'در انتظار','successful'=>'موفق','unsuccessful'=>'ناموفق/لغوشده','failed'=>'ناموفق','cancelled'=>'لغو شده']])
<div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-right">مشتری</th><x-admin.sortable-heading label="درگاه" column="provider" :sort="$sort" /><x-admin.sortable-heading label="مبلغ" column="amount" :sort="$sort" /><x-admin.sortable-heading label="وضعیت" column="status" :sort="$sort" /><th>حسابرو</th><th>Authority</th><x-admin.sortable-heading label="زمان" column="created_at" :sort="$sort" /><th></th></tr></thead><tbody class="divide-y">@forelse($payments as $payment)<tr><td class="px-5 py-4">
@adminRoute('admin.customers.show')
<a class="font-black text-slate-900" href="{{ route('admin.customers.show',$payment->customer) }}">{{ $payment->customer?->name }}</a>
@endadminRoute
<div class="text-xs text-slate-400">{{ $payment->customer?->email }}</div></td><td>{{ $payment->provider }}</td><td class="font-black">{{ $wallets->format($payment->amount,$payment->currency) }}</td><td><x-admin.status-badge :value="$payment->status" /></td><td class="px-3"><span>{{ ['queued' => 'در صف', 'submitted' => 'ثبت شده', 'failed' => 'ناموفق'][$payment->hesabro_status] ?? 'ارسال نشده' }}</span>@if($payment->isSuccessful() && !in_array($payment->hesabro_status, ['queued', 'submitted'], true))
@adminRoute('admin.billing.payments.hesabro.submit')
<form method="POST" action="{{ route('admin.billing.payments.hesabro.submit', $payment) }}" class="mt-1">@csrf<button class="text-xs font-black text-blue-600">ارسال به حسابرو</button></form>
@endadminRoute
@endif</td><td dir="ltr">{{ $payment->authority ?: '—' }}</td><td>{{ \Morilog\Jalali\Jalalian::fromCarbon($payment->created_at)->format('Y/m/d H:i') }}</td><td>
@adminRoute('admin.billing.payments.show')
<x-admin.icon-action :href="route('admin.billing.payments.show',$payment)" label="جزئیات پرداخت" icon="view" tone="primary" />
@endadminRoute
</td></tr>@empty<tr><td colspan="8" class="p-10 text-center text-slate-500">پرداختی با این فیلترها پیدا نشد.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $payments->links() }}</div></div>
</div>@endsection
