@extends('errors.layout')

@section('title', 'خطای سرویس')
@section('code', strtr((string) $exception->getStatusCode(), array_combine(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'])))
@section('heading', 'خطای سرویس')
@section('description', 'در پردازش درخواست مشکلی پیش آمده است. لطفا کمی بعد دوباره تلاش کنید.')
