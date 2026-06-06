@extends('customer.layout')

@section('title', 'Console')
@section('header_title', $server->name)
@section('header_subtitle', 'دسترسی مستقیم به صفحه ماشین از مسیر امن پنل')
@section('breadcrumbs')
    <a href="{{ route('customer.servers.index', [], false) }}" class="transition hover:text-[#0069FF]">سرورها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
    <a href="{{ route('customer.servers.show', $server, false) }}" class="truncate transition hover:text-[#0069FF]" dir="ltr">{{ $server->name }}</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
    <span class="truncate text-slate-700">Console</span>
@endsection

@php($activeNav = 'servers')

@section('content')
    <section
        x-data="customerVmConsole({
            sessionUrl: @js($consoleSessionUrl),
            csrf: @js(csrf_token()),
        })"
        x-init="connect()"
        class="space-y-4"
    >
        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-black text-[#0069FF]">کنسول ماشین مجازی</p>
                <h2 class="mt-1 truncate text-xl font-black text-slate-950" dir="ltr">{{ $server->name }}</h2>
                <p class="mt-1 truncate text-sm font-bold text-slate-500" dir="ltr">{{ $server->ip_address ?: 'IP pending' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="reconnect()" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">
                    اتصال مجدد
                </button>
                <button type="button" @click="disconnect()" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    قطع اتصال
                </button>
                <a href="{{ route('customer.servers.show', $server, false) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    جزئیات سرور
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-[1.75rem] border border-slate-900 bg-slate-950 shadow-2xl shadow-slate-950/20">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full" :class="connected ? 'bg-emerald-400' : (loading ? 'animate-pulse bg-amber-400' : 'bg-slate-500')"></span>
                    <span class="text-sm font-black" x-text="statusText">در حال اتصال...</span>
                </div>
                <span class="text-xs font-bold text-slate-400" dir="ltr">noVNC</span>
            </div>

            
            <div class="relative h-[68vh] min-h-[420px] bg-black" dir="ltr">
                <div x-ref="screen" class="absolute inset-0"></div>
                <div x-show="loading || error" class="absolute inset-0 grid place-items-center bg-slate-950/80 p-6 text-center text-white">
                    <div>
                        <div x-show="loading" class="mx-auto mb-4 size-10 animate-spin rounded-full border-4 border-white/20 border-t-white"></div>
                        <p class="text-lg font-black" x-text="error || statusText">در حال اتصال...</p>
                        <p x-show="error" class="mt-2 max-w-xl text-sm font-bold leading-7 text-slate-300">اگر خطا ادامه داشت، وضعیت ماشین مجازی را بررسی کنید یا با پشتیبانی تماس بگیرید.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
