@extends('layouts.admin')

@section('title', 'Console')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
    @endif

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
                <p class="text-xs font-black text-[#0069FF]">Admin VM Console · {{ $vm->customer?->name }}</p>
                <h1 class="mt-1 truncate text-xl font-black text-slate-950" dir="ltr">{{ $vm->name }}</h1>
                <p class="mt-1 truncate text-sm font-bold text-slate-500" dir="ltr">{{ $vm->node ?: 'node-not-set' }} · VMID {{ $vm->vmid ?: '-' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="reconnect()" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">
                    اتصال مجدد
                </button>
                <button type="button" @click="disconnect()" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    قطع اتصال
                </button>
                <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    جزئیات سرور
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-[1.75rem] border border-slate-900 bg-slate-950 shadow-2xl shadow-slate-950/20">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full" :class="connected ? 'bg-[#B8D6FF]' : (loading ? 'animate-pulse bg-amber-400' : 'bg-slate-500')"></span>
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
                        <p x-show="error" class="mt-2 max-w-xl text-sm font-bold leading-7 text-slate-300">اگر خطا ادامه داشت، وضعیت VM و سرویس console proxy روی سرور برنامه را بررسی کنید.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
