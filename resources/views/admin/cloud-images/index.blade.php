@extends('layouts.admin')
@section('title', 'Cloud Images')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">Cloud Images</h1>
            <p class="mt-2 text-sm text-slate-500">Published Proxmox template VMIDs available for cloud-init virtual machine creation.</p>
        </div>
        <a href="{{ route('admin.cloud-images.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Image جدید</a>
    </div>
    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($images as $image)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">{{ $image->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ ucfirst((string) $image->os_family) }} {{ $image->os_version }} · {{ $image->slug }}</p>
                    </div>
                    <x-admin.status-badge :value="$image->is_active ? 'active' : 'inactive'" />
                </div>
                <div class="mt-5 space-y-2 text-sm text-slate-600">
                    <p><span class="font-black text-slate-800">Proxmox:</span> {{ $image->proxmoxServer?->name }}</p>
                    <p><span class="font-black text-slate-800">Mappings:</span> {{ $image->nodeMappings->where('is_enabled', true)->count() }} node فعال</p>
                    <p><span class="font-black text-slate-800">Minimum:</span> {{ $image->min_cpu_cores }} CPU / {{ $image->min_ram_gb }}GB RAM / {{ $image->min_disk_gb }}GB Disk</p>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($image->nodeMappings as $mapping)
                        <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs" dir="ltr">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-black">{{ $mapping->node }} · VMID {{ $mapping->template_vmid }}</span>
                                <x-admin.status-badge :value="$mapping->is_enabled ? 'enabled' : 'disabled'" />
                            </div>
                            <p class="mt-1 text-slate-500">{{ $mapping->storage ?: 'default storage' }} · {{ $mapping->network_bridge }} · {{ $mapping->verified_at ? 'verified '.$mapping->verified_at->diffForHumans() : 'not verified' }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <x-admin.icon-action :href="route('admin.cloud-images.edit', $image)" label="ویرایش Cloud Image" icon="edit" tone="primary" />
                    <form method="POST" action="{{ route('admin.cloud-images.destroy', $image) }}" onsubmit="return confirm('آیا از حذف این Cloud Image مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-action type="submit" label="حذف Cloud Image" icon="delete" tone="danger" />
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">Cloud image ثبت نشده است.</div>
        @endforelse
    </div>
</div>
@endsection
