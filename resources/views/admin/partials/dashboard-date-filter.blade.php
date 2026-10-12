@if (! empty($filters['from']) || ! empty($filters['to']))
    <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
        <span>بازه {{ ($filters['date_basis'] ?? '') === 'activity' ? 'فعالیت' : 'ایجاد' }}: {{ $filters['from'] ?? '—' }} تا {{ $filters['to'] ?? '—' }}</span>
        <input type="hidden" name="from" value="{{ $filters['from'] ?? '' }}">
        <input type="hidden" name="to" value="{{ $filters['to'] ?? '' }}">
        @if (isset($filters['date_basis']))<input type="hidden" name="date_basis" value="{{ $filters['date_basis'] }}">@endif
        <a href="{{ request()->fullUrlWithoutQuery(['from', 'to', 'date_basis', 'page']) }}" class="min-h-10 py-2 font-semibold text-[#0059DB] hover:underline">حذف بازه</a>
    </div>
@endif
