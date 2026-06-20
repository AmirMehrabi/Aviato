@extends('layouts.admin')

@section('title', 'Edit Location')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    <form method="POST" action="{{ route('admin.infrastructure-locations.update', $location) }}" class="max-w-5xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <div>
            <h1 class="text-2xl font-black">Edit {{ $location->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $location->provider }} / {{ $location->remote_name }}</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <x-form.input name="name" label="Customer-facing location name" :value="$location->name" />
            <x-form.input name="sort_order" type="number" label="Sort order" :value="$location->sort_order" dir-ltr />
            <x-form.checkbox name="is_active" label="Active" :checked="$location->is_active" />
            <x-form.checkbox name="maintenance_mode" label="Maintenance mode" :checked="$location->maintenance_mode" />
        </div>

        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h2 class="text-sm font-black">Sellable bundles</h2>
            <div class="mt-4 grid gap-3">
                @foreach ($bundles as $bundle)
                    @php($mapping = $mappings->get($bundle->id))
                    <label class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[24px_minmax(0,1fr)_260px]">
                        <input type="checkbox" name="bundle_ids[]" value="{{ $bundle->id }}" @checked($mapping?->is_active) class="mt-1 size-4 rounded border-slate-300 text-[#0069FF]">
                        <span>
                            <span class="block font-black">{{ $bundle->name }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $bundle->cpu_cores }} CPU / {{ $bundle->ram_gb }}GB / {{ $bundle->disk_gb }}GB</span>
                        </span>
                        @if ($location->isHetzner())
                            <select name="server_type_ids[{{ $bundle->id }}]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <option value="">Select Hetzner type</option>
                                @foreach ($serverTypes as $serverType)
                                    <option value="{{ $serverType->id }}" @selected((int) $mapping?->hetzner_server_type_id === (int) $serverType->id)>{{ $serverType->name }} - {{ $serverType->cpu_cores }} CPU / {{ $serverType->memory_gb }}GB / {{ $serverType->disk_gb }}GB</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
            </div>
        </section>

        <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Save location</button>
    </form>
</div>
@endsection
