<style>
    .colo-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; padding: 24px; }
    .colo-btn { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border-radius: 10px; padding: 10px 20px; background: #2563eb; color: #fff; font-size: 14px; font-weight: 800; }
    .colo-btn:focus-visible, .colo-input:focus-visible { outline: 3px solid #60a5fa; outline-offset: 3px; }
    .colo-input { display: block; width: 100%; min-height: 44px; margin-top: 8px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; background: #fff; color: #0f172a; }
    .colo-label { display: block; font-size: 13px; font-weight: 700; color: #334155; }
</style>
<div class="mb-6 flex flex-wrap gap-5 text-sm font-bold text-blue-700"><a href="{{ route('admin.colocation.index') }}">همه سرویس‌ها و صف نصب</a>@isset($customer)<a href="{{ route('admin.customers.show', $customer) }}">پروفایل مشتری</a>@endisset</div>
@if(session('status'))<p role="status" class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm leading-7 text-emerald-900">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm leading-7 text-red-900">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
