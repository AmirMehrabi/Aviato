@extends('layouts.admin')

@section('title', 'ماشین‌های ثبت‌نشده')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-bold text-[#0069FF]">موجودی Proxmox</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950">ماشین‌های ثبت‌نشده</h1>
            <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500">مهمان‌هایی که در Proxmox وجود دارند اما هنوز به یک VM پنل متصل نیستند.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <label class="text-xs font-bold text-slate-500">سرور
                <select name="server_id" class="mt-1 block rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">همه سرورها</option>
                    @foreach($servers as $server)<option value="{{ $server->id }}" @selected($selectedServerId === $server->id)>{{ $server->name }}</option>@endforeach
                </select>
            </label>
        </form>
    </div>

    @if(session('error'))<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ session('error') }}</div>@endif

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @if($candidates->isEmpty())
            <div class="p-10 text-center"><div class="text-4xl font-black text-emerald-600">۰</div><h2 class="mt-3 text-xl font-black text-slate-950">موردی برای ثبت وجود ندارد</h2><p class="mt-2 text-sm text-slate-500">ابتدا موجودی سرورهای Proxmox را همگام‌سازی کنید.</p></div>
        @else
            <div class="overflow-x-auto"><table class="min-w-full text-right text-sm"><thead class="border-b border-slate-100 bg-slate-50 text-xs font-bold text-slate-500"><tr><th class="px-5 py-4">مهمان</th><th class="px-5 py-4">سرور / نود</th><th class="px-5 py-4">نوع</th><th class="px-5 py-4">وضعیت</th><th class="px-5 py-4">منابع</th><th class="px-5 py-4">ثبت</th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach($candidates as $guest)
                    <tr class="align-top"><td class="px-5 py-5"><div class="font-black text-slate-950">{{ $guest['name'] }}</div><div class="mt-1 font-mono text-xs text-slate-500" dir="ltr">VMID {{ $guest['vmid'] }}</div></td><td class="px-5 py-5 text-slate-700">{{ $guest['server_name'] }}<div class="mt-1 text-xs text-slate-500" dir="ltr">{{ $guest['node'] ?: '—' }}</div></td><td class="px-5 py-5"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold uppercase" dir="ltr">{{ $guest['guest_type'] }}</span></td><td class="px-5 py-5"><span class="rounded-md px-2 py-1 text-xs font-bold {{ $guest['status'] === 'running' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $guest['status'] === 'running' ? 'روشن' : 'خاموش' }}</span></td><td class="px-5 py-5 text-slate-700" dir="ltr">{{ $guest['cpu_cores'] }} CPU · {{ $guest['ram_gb'] }} GB · {{ $guest['disk_gb'] }} GB</td><td class="px-5 py-5"><form method="POST" action="{{ route('admin.unprovisioned-virtual-machines.claim') }}" class="min-w-[280px] space-y-2">@csrf<input type="hidden" name="proxmox_server_id" value="{{ $guest['server_id'] }}"><input type="hidden" name="vmid" value="{{ $guest['vmid'] }}"><select name="customer_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">مشتری</option>@foreach($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select><select name="vm_bundle_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">باندل صورتحساب</option>@foreach($bundles as $bundle)<option value="{{ $bundle->id }}">{{ $bundle->name }} — {{ $bundle->cpu_cores }}C / {{ $bundle->ram_gb }}GB / {{ $bundle->disk_gb }}GB</option>@endforeach</select><select name="ip_address_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">IP پنل</option>@foreach($ipAddresses as $ip)<option value="{{ $ip->id }}">{{ $ip->address }} — {{ $ip->pool?->proxmoxServer?->name }}{{ $ip->pool?->node ? ' / '.$ip->pool->node : '' }}</option>@endforeach</select><button class="w-full rounded-lg bg-[#0069FF] px-3 py-2 text-sm font-bold text-white hover:bg-[#0050D0]">ثبت و تخصیص</button></form></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </div>
</div>
@endsection
