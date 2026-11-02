<style>
    .colo-card { border: 1px solid #e2e8f0; border-radius: 20px; background: #fff; padding: 24px; }
    .colo-btn { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; padding: 10px 20px; background: #2563eb; color: #fff; font-size: 14px; font-weight: 800; }
    .colo-btn:hover { background: #1d4ed8; }
    .colo-btn:focus-visible, .colo-input:focus-visible, .colo-link:focus-visible { outline: 3px solid #60a5fa; outline-offset: 3px; }
    .colo-input { display: block; min-height: 44px; width: 100%; margin-top: 8px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; background: #fff; color: #0f172a; }
    .colo-link { color: #1d4ed8; font-weight: 800; }
    @media print {
        .colo-no-print, aside[aria-label="منوی مشتری"], main > header { display: none !important; }
        body > div.lg\:grid { display: block !important; }
        .colo-card { break-inside: avoid; }
    }
</style>
<nav aria-label="کولوکیشن" class="colo-no-print mb-6 flex flex-wrap items-center gap-3 border-b border-slate-200 pb-4 text-sm">
    <a class="rounded-xl px-4 py-3 font-bold {{ request()->routeIs('*customer.colocation.index') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('customer.colocation.index', [], false) }}">سرویس‌ها و پلن‌ها</a>
    <a class="rounded-xl px-4 py-3 font-bold {{ request()->routeIs('*customer.colocation.invoices.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('customer.colocation.invoices.index', [], false) }}">صورتحساب‌های کولوکیشن</a>
    <span class="text-xs text-slate-500">فضای کاری: {{ $activeProject->name }}</span>
</nav>
@if(session('status'))<p role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-7 text-emerald-900">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-900"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
