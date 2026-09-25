@extends('customer.layout')

@section('title', $server->display_name.' · '.(['overview' => 'نمای کلی', 'resources' => 'منابع', 'billing' => 'صورتحساب', 'upgrade' => 'ارتقا', 'rebuild' => 'بازسازی', 'delete' => 'حذف', 'activity' => 'فعالیت'][$tab] ?? 'سرور'))
@section('header_title', $server->display_name)
@section('full_width_header')
    @include('customer.servers.partials.header')
@endsection

@php($activeNav = 'servers')

@section('content')
    <div class="mx-auto max-w-6xl">
        @include('customer.servers.tabs.'.$tab)
    </div>
@endsection
