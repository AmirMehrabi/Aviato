@extends('layouts.marketing')
@section('title', 'ادامه ثبت دامنه | آویاتو')
@section('content')
    <section class="px-4 pb-16 pt-32">
        <div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold leading-10">برای ادامه ثبت دامنه با ما در ارتباط باشید.</h1>
            <p class="mt-4 leading-8">خرید دامنه برای حساب شما هنوز فعال نیست. برای بررسی و فعال‌سازی دسترسی، با پشتیبانی تماس بگیرید. نام انتخابی شما:</p>
            <p dir="ltr" class="mt-4 break-all text-lg font-bold">{{ $domain }}</p>
            <a href="{{ route('contact') }}" class="mt-6 inline-flex rounded-xl bg-[#0069FF] px-6 py-3 text-white">تماس با پشتیبانی</a>
        </div>
    </section>
@endsection
