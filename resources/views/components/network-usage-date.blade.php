@props(['date'])

@php($day = \Illuminate\Support\Carbon::parse($date, config('app.customer_timezone')))
<time datetime="{{ $date }}" dir="ltr">{{ \App\Support\PersianDigits::convert(\App\Support\Jalali::format($day, 'Y/m/d')) }}</time>
