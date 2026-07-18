@extends('customer.layout')

@section('title', 'فضای ذخیره‌سازی')
@section('header_title', 'فضای ذخیره‌سازی S3')
@section('header_subtitle', 'ذخیره‌سازی استاندارد برای فایل‌ها، بکاپ‌ها و داده‌های برنامه شما')

@php($activeNav = 'storage')

@section('content')
    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold leading-7 text-emerald-800">{{ session('status') }}</div>
        @endif

        @if (session('storage_credentials'))
            <section class="rounded-2xl border border-amber-300 bg-amber-50 p-5">
                <p class="text-sm font-black text-amber-950">کلید جدید — این اطلاعات فقط همین یک بار نمایش داده می‌شود</p>
                <p class="mt-2 text-sm leading-7 text-amber-900">رمز مخفی را در password manager ذخیره کنید. پس از خروج از این صفحه امکان نمایش دوباره آن وجود ندارد.</p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2" dir="ltr">
                    <div class="rounded-xl bg-white/80 p-3"><dt class="text-xs font-bold text-slate-500">Access key ID</dt><dd class="mt-1 break-all font-mono text-sm font-black text-slate-950">{{ session('storage_credentials.access_key_id') }}</dd></div>
                    <div class="rounded-xl bg-white/80 p-3"><dt class="text-xs font-bold text-slate-500">Secret access key</dt><dd class="mt-1 break-all font-mono text-sm font-black text-slate-950">{{ session('storage_credentials.secret') }}</dd></div>
                </dl>
            </section>
        @endif

        <section class="rounded-2xl border border-blue-100 bg-gradient-to-br from-[#f4f8ff] to-white p-6 shadow-sm">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-black tracking-[0.2em] text-[#0069FF]">PROJECT STORAGE</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">فضای کاری {{ $activeProject->name }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-600">باکت بسازید و با AWS CLI، SDKهای رسمی یا هر ابزار سازگار با S3 به آن متصل شوید.</p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-white px-4 py-3 text-left" dir="ltr"><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">S3 endpoint</p><code class="mt-1 block text-sm font-black text-[#0069FF]">{{ $endpoint }}</code></div>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-black text-slate-950">باکت‌ها</h2><p class="mt-1 text-sm text-slate-500">هر باکت یک فضای نام مستقل برای فایل‌های شماست.</p></div><span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $buckets->count() }} باکت</span></div>
                <form method="POST" action="{{ route('customer.storage.buckets.store') }}" class="mt-5 flex gap-2">@csrf<input name="name" value="{{ old('name') }}" required maxlength="63" dir="ltr" placeholder="my-project-files" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold focus:border-[#0069FF] focus:outline-none"><button class="rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white hover:bg-[#0050D0]">ساخت باکت</button></form>
                @error('name')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                <div class="mt-5 space-y-2">@forelse($buckets as $bucket)<div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3"><div><p class="font-mono text-sm font-black text-slate-900" dir="ltr">{{ $bucket->name }}</p><p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $bucket->region }} · {{ number_format($bucket->usage_bytes) }} bytes</p></div><form method="POST" action="{{ route('customer.storage.buckets.destroy', $bucket) }}" onsubmit="return confirm('این باکت حذف شود؟ باکت باید خالی باشد.')">@csrf @method('DELETE')<button class="text-xs font-black text-red-600 hover:text-red-800">حذف</button></form></div>@empty<p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm font-bold text-slate-500">هنوز باکتی نساخته‌اید.</p>@endforelse</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div><h2 class="text-lg font-black text-slate-950">کلیدهای اتصال</h2><p class="mt-1 text-sm leading-6 text-slate-500">برای اتصال ابزارها به S3 یک کلید جدا بسازید و هر زمان لازم بود لغو کنید.</p></div>
                <form method="POST" action="{{ route('customer.storage.access-keys.store') }}" class="mt-5 flex gap-2">@csrf<input name="description" maxlength="100" placeholder="مثلا: بکاپ سایت" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold focus:border-[#0069FF] focus:outline-none"><button class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white hover:bg-slate-800">ساخت کلید</button></form>
                @error('description')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                <div class="mt-5 space-y-2">@forelse($accessKeys as $key)<div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3"><div><p class="font-mono text-sm font-black text-slate-900" dir="ltr">{{ $key->access_key_id }}</p><p class="mt-1 text-xs text-slate-500">{{ $key->description ?: 'بدون توضیح' }} · {{ $key->last_used_at ? \App\Support\Jalali::format($key->last_used_at) : 'هنوز استفاده نشده' }}</p></div><form method="POST" action="{{ route('customer.storage.access-keys.destroy', $key) }}" onsubmit="return confirm('این کلید لغو شود؟')">@csrf @method('DELETE')<button class="text-xs font-black text-red-600 hover:text-red-800">لغو</button></form></div>@empty<p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm font-bold text-slate-500">هنوز کلیدی نساخته‌اید.</p>@endforelse</div>
            </section>
        </div>
    </div>
@endsection
