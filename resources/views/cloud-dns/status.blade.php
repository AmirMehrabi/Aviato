@php($connection = $zone->connectionState())
<span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $connection['healthy'] ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ $connection['label'] }}</span>
