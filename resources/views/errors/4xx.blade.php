@extends('errors.layout')

@section('title', 'درخواست قابل پردازش نیست')
@section('code', strtr((string) $exception->getStatusCode(), array_combine(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'])))
@section('heading', 'درخواست قابل پردازش نیست')
@section('description', 'درخواست شما قابل پردازش نیست. لطفا به خانه برگردید یا با پشتیبانی تماس بگیرید.')
