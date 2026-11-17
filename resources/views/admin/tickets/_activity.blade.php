@php
    $activity = $ticket->events->whereIn('type', ['created', 'assignment_changed', 'status_changed']);
    $activityLabels = [
        'created' => 'تیکت ایجاد شد',
        'assignment_changed' => 'مسیر رسیدگی تغییر کرد',
        'status_changed' => 'وضعیت تغییر کرد',
    ];
@endphp

<details class="rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:px-5">
    <summary class="cursor-pointer py-1 text-sm font-black text-slate-900">فعالیت تیکت</summary>
    <ul class="mt-3 divide-y divide-slate-100">
        @forelse($activity as $event)
            <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2 text-xs">
                <span class="text-slate-700">
                    <strong>{{ $activityLabels[$event->type] }}</strong>
                    @if($event->type === 'status_changed' && isset($statuses[$event->payload['status']]))
                        · {{ $statuses[$event->payload['status']] }}
                    @endif
                    @if($event->actor?->name)
                        · {{ $event->actor->name }}
                    @endif
                </span>
                <time datetime="{{ $event->created_at->toIso8601String() }}" dir="ltr" class="text-slate-500">{{ \App\Support\Jalali::format($event->created_at) }}</time>
            </li>
        @empty
            <li class="py-2 text-xs text-slate-500">فعالیتی ثبت نشده است.</li>
        @endforelse
    </ul>
</details>
