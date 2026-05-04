@props([
    'columns' => [],
    'emptyTitle' => 'موردی پیدا نشد',
    'emptyDescription' => 'فیلترها را تغییر دهید یا رکورد جدید بسازید.',
    'emptyAction' => null,
])

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-right text-sm">
            <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" class="whitespace-nowrap px-5 py-4 {{ $column['class'] ?? '' }}">{{ $column['label'] ?? $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($empty)
        {{ $empty }}
    @endisset

    @isset($pagination)
        <div class="border-t border-slate-100 px-5 py-4">
            {{ $pagination }}
        </div>
    @endisset
</div>
