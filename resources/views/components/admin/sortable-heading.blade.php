@props([
    'label',
    'column',
    'sort',
    'class' => '',
])

@php
    $active = ($sort['column'] ?? null) === $column;
    $currentDirection = $active ? ($sort['direction'] ?? 'asc') : null;
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = request()->except(['page', 'sort', 'direction']);
    $query['sort'] = $column;
    $query['direction'] = $nextDirection;
    $url = request()->url().'?'.http_build_query($query);
    $preferenceUrl = route('admin.table-preferences.update', ['tableKey' => $sort['table']]);
    $ariaSort = $active ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th scope="col" aria-sort="{{ $ariaSort }}" class="whitespace-nowrap p-0 {{ $class }}">
    <a
        href="{{ $url }}"
        data-admin-sort-link
        data-preference-url="{{ $preferenceUrl }}"
        data-sort-column="{{ $column }}"
        data-sort-direction="{{ $nextDirection }}"
        class="group flex min-h-12 items-center gap-2 px-5 py-3 font-black transition-colors hover:bg-slate-100/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#0069FF]"
        aria-label="مرتب‌سازی {{ $label }}، {{ $nextDirection === 'asc' ? 'صعودی' : 'نزولی' }}"
    >
        <span>{{ $label }}</span>
        <x-admin.icon
            :name="$active ? ($currentDirection === 'asc' ? 'sort-asc' : 'sort-desc') : 'sort'"
            class="{{ $active ? 'text-[#0069FF]' : 'text-slate-300 group-hover:text-slate-500' }}"
        />
    </a>
</th>
