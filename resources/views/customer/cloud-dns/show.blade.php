@extends('customer.layout')
@section('title', 'Cloud DNS · '.$zone->name)
@section('header_title', 'Cloud DNS')
@section('header_subtitle', $zone->name)
@php($activeNav = 'cloud-dns')
@section('content')
    @include('cloud-dns.zone')
@endsection
