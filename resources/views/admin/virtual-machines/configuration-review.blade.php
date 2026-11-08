@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'مرور تغییرات ماشین مجازی')
@section('content')
@php
    $title = match ($quote['kind']) { 'pricing' => 'تغییر قیمت همین ماشین', 'sync' => 'پذیرش سخت‌افزار Proxmox', default => 'تغییر سخت‌افزار' };
    $labels = ['cpu_cores' => 'پردازنده (vCPU)', 'ram_gb' => 'حافظه (GiB)', 'disk_gb' => 'دیسک اصلی (GiB)', 'pricing_mode' => 'روش قیمت‌گذاری'];
    $modes = ['inherit' => 'پیروی از پلن', 'custom' => 'قیمت اختصاصی', 'resources' => 'نرخ منابع'];
@endphp
<div class="mx-auto max-w-4xl px-4 py-8 md:px-8">
    <a href="{{ route('admin.virtual-machines.show', $vm) }}#configuration" class="text-sm font-bold text-[#0069FF]">بازگشت به تنظیمات ماشین</a>
    <div class="mt-6 flex flex-wrap gap-3 text-xs font-bold"><span class="rounded-full bg-slate-100 px-3 py-2 text-slate-500">۱ · انتخاب تغییر</span><span aria-current="step" class="rounded-full bg-blue-50 px-3 py-2 text-blue-700">۲ · مرور و تأیید</span><span class="rounded-full bg-slate-100 px-3 py-2 text-slate-500">۳ · ثبت و پیگیری</span></div>
    <h1 class="mt-6 text-2xl font-black text-slate-950">{{ $title }}</h1>
    <p class="mt-2 text-sm leading-7 text-slate-500"><span dir="ltr" class="font-bold text-slate-700">{{ $vm->display_name }}</span> · هنوز تغییری اعمال نشده است. این پیش‌نمایش تا ۱۰ دقیقه معتبر است.</p>
    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto p-5"><table class="w-full text-right text-sm"><thead class="border-b border-slate-100 text-xs text-slate-500"><tr><th class="pb-4">تنظیم</th><th>اکنون</th><th>پس از تأیید</th></tr></thead><tbody class="divide-y divide-slate-100">
            @foreach ($labels as $field => $label)
                @if ($quote['before'][$field] !== $quote['after'][$field])
                    <tr><th class="py-4 font-medium">{{ $label }}</th><td class="text-slate-500">{{ $field === 'pricing_mode' ? $modes[$quote['before'][$field]] : $quote['before'][$field] }}</td><td class="font-bold text-[#0069FF]">{{ $field === 'pricing_mode' ? $modes[$quote['after'][$field]] : $quote['after'][$field] }}</td></tr>
                @endif
            @endforeach
            @adminAbility('billing.read')
            @foreach (['running' => 'هزینه معادل ماهانه · روشن', 'stopped' => 'هزینه معادل ماهانه · خاموش'] as $key => $label)
                <tr><th class="py-4 font-medium">{{ $label }}</th><td class="text-slate-500">{{ $money->format($quote['before_prices'][$key]) }}</td><td class="font-bold text-slate-950">{{ $money->format($quote['after_prices'][$key]) }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $quote['after_prices'][$key] === $quote['before_prices'][$key] ? 'بدون تغییر' : (($quote['after_prices'][$key] > $quote['before_prices'][$key] ? 'افزایش ' : 'کاهش ').$money->format(abs($quote['after_prices'][$key] - $quote['before_prices'][$key]))) }}</span></td></tr>
            @endforeach
            @endadminAbility
        </tbody></table></div>
        <div class="border-t border-slate-100 bg-slate-50 p-5 text-sm leading-7 text-slate-600"><p><strong>دلیل:</strong> {{ $quote['reason'] }}</p><p>کارکرد گذشته با قیمت قبلی حفظ می‌شود. قیمت جدید از زمان ثبت مؤثر خواهد بود؛ بکاپ، دیسک اضافه و مازاد ترافیک جداگانه محاسبه می‌شوند. مبالغ نمایشی شامل مالیاتِ قابل‌اعمال و بر مبنای ۷۳۰ ساعت هستند.</p></div>
    </section>
    @if ($quote['kind'] === 'hardware')
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-900"><p class="font-bold">{{ $quote['remote']['status'] === 'running' ? 'این تغییر ممکن است باعث قطع موقت سرویس شود.' : 'سرور پس از تغییر خاموش می‌ماند.' }}</p><p>ابتدا سرور به‌صورت کنترل‌شده خاموش می‌شود؛ پس از تأیید منابع، وضعیت قبلی بازیابی می‌شود. محدودیت کیف پول می‌تواند از روشن شدن جلوگیری کند. در صورت افزایش دیسک، گسترش پارتیشن داخل سیستم‌عامل را نیز بررسی کنید.</p></div>
    @elseif ($quote['kind'] === 'sync')
        <p class="mt-5 rounded-xl bg-blue-50 p-4 text-sm leading-7 text-blue-900">تنظیمات فعلی Proxmox در پنل پذیرفته می‌شود. هیچ فرمان تغییر سخت‌افزاری به سرور ارسال نخواهد شد.</p>
    @endif
    <form method="POST" action="{{ route('admin.virtual-machines.configuration.confirm', $vm) }}" class="mt-6" x-data="{ busy: false }" @submit="busy = true">
        @csrf <input type="hidden" name="quote" value="{{ $quote['token'] }}">
        <label class="flex items-start gap-3 text-sm leading-7 text-slate-700"><input type="checkbox" name="confirm" value="1" required class="mt-2">منابع، هزینه و تأثیر این تغییر را بررسی و تأیید می‌کنم.</label>
        <div class="mt-5 flex flex-wrap items-center gap-4"><button :disabled="busy" class="rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white hover:bg-blue-700 disabled:opacity-50" x-text="busy ? 'در حال ثبت…' : 'تأیید و ثبت تغییرات'">تأیید و ثبت تغییرات</button><a href="{{ route('admin.virtual-machines.show', $vm) }}#configuration" class="text-sm font-bold text-slate-500">انصراف و بازگشت</a></div>
    </form>
</div>
@endsection
