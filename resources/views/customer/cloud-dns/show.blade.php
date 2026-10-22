@extends('customer.layout')
@section('title', 'دی‌ان‌اس ابری · '.$zone->name)
@section('header_title', 'دی‌ان‌اس ابری')
@section('header_subtitle', 'مدیریت اتصال دامنه و رکوردها')
@section('compact_header', '1')
@php($activeNav = 'cloud-dns')
@section('content')
    @include('cloud-dns.zone')
@endsection
