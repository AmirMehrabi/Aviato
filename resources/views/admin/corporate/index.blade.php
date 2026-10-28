@extends('layouts.admin')
@section('title', 'درخواست‌های حقوقی')
@section('content')
<div class="p-6 space-y-5">
    <h1 class="text-2xl font-black">درخواست‌های احراز هویت حقوقی <span class="text-blue-600">{{ $pendingCount }} در انتظار بررسی</span></h1>
    @adminRoute('admin.settings.corporate.edit')<a href="{{ route('admin.settings.corporate.edit') }}" class="text-blue-600">تنظیمات و قالب‌های پیامک</a>@endadminRoute
    <form class="flex flex-wrap gap-3"><input name="q" value="{{ request('q') }}" placeholder="نام شرکت، شناسه یا مشتری" class="rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"><select name="status" class="rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"><option value="">همه وضعیت‌ها</option>@foreach(\App\Models\CorporateRequest::statuses() as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select><button class="rounded-xl bg-blue-600 p-3 text-white">جستجو</button></form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full text-right text-sm"><thead><tr><th class="p-4">شماره</th><th>شرکت / شناسه ملی</th><th>مشتری</th><th>وضعیت</th><th>ارسال</th><th></th></tr></thead><tbody>
        @forelse($requests as $corporate)<tr class="border-t"><td class="p-4">{{ $corporate->id }}</td><td>{{ $corporate->company['name'] }}<br>{{ $corporate->national_id }}</td><td>{{ $corporate->customer->name }}</td><td>{{ \App\Models\CorporateRequest::statuses()[$corporate->status] }}</td><td>{{ $corporate->submitted_at ? \App\Support\Jalali::format($corporate->submitted_at) : '—' }}</td><td><a class="text-blue-600" href="{{ route('admin.corporate.show', $corporate) }}">بررسی</a></td></tr>@empty<tr><td colspan="6" class="p-5">درخواستی یافت نشد.</td></tr>@endforelse
    </tbody></table></div>{{ $requests->links() }}
</div>
@endsection
