@extends('layouts.marketing')

@section('title', 'هدیه الکامپ آویاتو | زیرساخت پروژه بعدی')
@section('description', 'کد هدیه الکامپ آویاتو را فعال کنید و پروژه بعدی‌تان را روی سرور ابری با NVMe، IP اختصاصی و پشتیبانی فارسی راه‌اندازی کنید.')
@section('body_class', 'bg-[#F5F8FD]')

@section('content')
<section class="relative isolate overflow-hidden px-4 pb-16 pt-28 md:px-8 md:pb-24 md:pt-36 lg:px-10">
    <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_15%_20%,#DCEEFF_0,transparent_34%),linear-gradient(145deg,#F8FBFF_0%,#EEF5FF_55%,#FFFFFF_100%)]"></div>
    <div class="absolute -left-24 top-20 -z-10 size-80 rounded-full border-[48px] border-[#0069FF]/5"></div>
    <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,1fr)_430px] lg:items-center">
        <div>
            <span class="inline-flex rounded-full border border-blue-200 bg-white/80 px-4 py-2 text-xs font-black text-[#0069FF] shadow-sm">هدیه ویژه الکامپ ۱۴۰۵</span>
            <h1 class="mt-6 max-w-4xl text-4xl font-black leading-[1.3] text-slate-950 sm:text-5xl lg:text-6xl">هدیه‌ات را فعال کن؛<br><span class="text-[#0069FF]">پروژه بعدی‌ات را بالا بیاور.</span></h1>
            <p class="mt-6 max-w-2xl text-base font-bold leading-8 text-slate-600 md:text-lg">از API و اپلیکیشن تا محیط تست؛ با منابع روشن، دیسک NVMe، IP اختصاصی و پشتیبانی فارسی شروع کن.</p>
            <div class="mt-8 grid max-w-2xl grid-cols-3 gap-3 text-center text-xs font-black text-slate-600 sm:text-sm">
                <div class="rounded-2xl border border-white bg-white/70 p-4 shadow-sm">NVMe پرسرعت</div>
                <div class="rounded-2xl border border-white bg-white/70 p-4 shadow-sm">IP اختصاصی</div>
                <div class="rounded-2xl border border-white bg-white/70 p-4 shadow-sm">پشتیبانی فارسی</div>
            </div>
        </div>

        <div id="claim" class="rounded-[2rem] border border-blue-100 bg-white p-6 shadow-2xl shadow-blue-200/50 sm:p-8">
            <p class="text-xs font-black text-[#0069FF]">کارت الکامپ دارید؟</p>
            <h2 class="mt-2 text-2xl font-black text-slate-950">کد هدیه را وارد کنید</h2>
            <p class="mt-3 text-sm font-bold leading-7 text-slate-500">مقدار و نوع هدیه پس از بررسی کد نشان داده می‌شود.</p>
            <form method="POST" action="{{ route('elecomp.claim') }}" class="mt-6" data-claim-form>
                @csrf
                <label for="elecomp-code" class="sr-only">کد هدیه</label>
                <input id="elecomp-code" name="code" value="{{ old('code') }}" required maxlength="64" autocomplete="off" spellcheck="false" dir="ltr" placeholder="AVT-XXXX-XXXX-XXXX-XXXX" class="h-14 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-center font-mono text-base font-black uppercase tracking-wider outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10">
                @error('code')<p class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
                <button class="mt-4 inline-flex min-h-14 w-full items-center justify-center rounded-xl bg-[#0069FF] px-6 font-black text-white shadow-lg shadow-blue-500/20 transition hover:bg-[#0050D0] active:scale-[.98]">بررسی و فعال‌سازی هدیه</button>
            </form>
            <p class="mt-4 text-center text-xs font-bold leading-6 text-slate-400">برای اعتبار مستقیم، پرداخت بانکی لازم نیست. هر کد فقط یک‌بار قابل استفاده است.</p>
        </div>
    </div>
</section>

<section class="bg-white px-4 py-20 md:px-8 lg:px-10">
    <div class="mx-auto max-w-7xl">
        <div class="max-w-3xl"><p class="text-sm font-black text-[#0069FF]">زیرساخت برای ساختن</p><h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">از ایده تا سرویس واقعی، بدون ابهام زیرساختی.</h2></div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([['API و اپلیکیشن','ماشین مجازی با دسترسی مدیریتی برای Docker، وب‌سرویس و پردازش پس‌زمینه.'],['تست و توسعه','یک محیط جدا برای CI، آزمایش نسخه جدید و تمرین، بدون درگیر کردن سرویس اصلی.'],['AI و پردازش','سرور قابل کنترل برای prototype، worker، صف و ابزارهای داده‌محور.']] as [$title,$body])
                <article class="rounded-[1.75rem] border border-slate-200 bg-[#FBFDFF] p-6"><span class="grid size-11 place-items-center rounded-xl bg-[#EAF3FF] font-black text-[#0069FF]">{{ $loop->iteration }}</span><h3 class="mt-5 text-xl font-black">{{ $title }}</h3><p class="mt-3 text-sm font-bold leading-7 text-slate-500">{{ $body }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-[#07172D] px-4 py-20 text-white md:px-8 lg:px-10">
    <div class="mx-auto max-w-7xl"><p class="text-sm font-black text-blue-300">سه قدم تا شروع</p><h2 class="mt-3 text-3xl font-black">کوتاه، روشن، قابل پیگیری.</h2><div class="mt-10 grid gap-5 md:grid-cols-3">@foreach ([['کدت را وارد کن','QR را اسکن یا کد روی کارت را تایپ کن.'],['حسابت را بساز','ثبت‌نام یا ورود کن تا هدیه به فضای کاری تو متصل شود.'],['سرورت را بساز','پلن، موقعیت و سیستم‌عامل را انتخاب کن و شروع کن.']] as [$title,$body])<article class="rounded-2xl border border-white/10 bg-white/5 p-6"><p class="text-3xl font-black text-blue-300">0{{ $loop->iteration }}</p><h3 class="mt-5 text-xl font-black">{{ $title }}</h3><p class="mt-3 text-sm font-bold leading-7 text-slate-300">{{ $body }}</p></article>@endforeach</div></div>
</section>

<a href="#claim" class="fixed inset-x-4 bottom-4 z-40 inline-flex min-h-13 items-center justify-center rounded-xl bg-[#0069FF] px-5 font-black text-white shadow-2xl shadow-blue-950/30 md:hidden">فعال‌سازی هدیه</a>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('elecomp-code');
    const hashCode = decodeURIComponent(location.hash.slice(1));
    if (hashCode && input) {
        input.value = hashCode;
        history.replaceState(null, '', location.pathname);
        document.getElementById('claim')?.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    input?.addEventListener('input', () => input.value = input.value.toUpperCase());
});
</script>
@endsection
