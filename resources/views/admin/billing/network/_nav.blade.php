<div class="mb-6 flex flex-wrap gap-2">
    @foreach([
        ['route' => 'admin.billing.network.index', 'label' => 'نمای کلی'],
        ['route' => 'admin.billing.network.ipdr', 'label' => 'اتصال IPDR'],
        ['route' => 'admin.billing.network.exceptions', 'label' => 'استثناها'],
        ['route' => 'admin.billing.network.reconciliation', 'label' => 'تطبیق'],
    ] as $item)
        <a href="{{ route($item['route']) }}" class="rounded-xl px-4 py-2.5 text-sm font-black {{ request()->routeIs($item['route']) ? 'bg-[#0069FF] text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-[#B8D6FF] hover:text-[#0069FF]' }}">{{ $item['label'] }}</a>
    @endforeach
</div>
