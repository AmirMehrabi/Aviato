@extends('customer.layout')

@section('title', 'کنسول')
@section('header_title', $server->display_name)
@section('header_subtitle', 'دسترسی مستقیم به صفحه ماشین از مسیر امن پنل')
@section('breadcrumbs')
    <a href="{{ route('customer.servers.index', [], false) }}" class="transition hover:text-[#0069FF]">سرورها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
    <a href="{{ route('customer.servers.show', $server, false) }}" class="truncate transition hover:text-[#0069FF]" dir="ltr">{{ $server->display_name }}</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
    <span class="truncate text-slate-700">کنسول</span>
@endsection

@php($activeNav = 'servers')

@section('content')
    @include('console.workspace', ['adminConsole' => false])
@endsection
