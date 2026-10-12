@if ($finance !== null || $activity['awaiting_tickets'] !== null)
    <div class="mt-6 grid items-start gap-5 {{ $finance !== null && $activity['awaiting_tickets'] !== null ? 'xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' : 'grid-cols-1' }}">
        @if ($finance !== null)
            @include('admin._dashboard-finance')
        @endif
        @if ($activity['awaiting_tickets'] !== null)
            <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="support-heading" x-data="{ tab: 'awaiting' }">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="support-heading" class="text-lg font-bold text-slate-950">پشتیبانی</h2>
                        <a :href="tab === 'awaiting' ? @js(route('admin.tickets.index', ['status' => 'open'])) : @js(route('admin.tickets.index', $reportRange + ['date_basis' => 'activity']))" class="min-h-10 py-2 text-sm font-semibold text-[#0059DB] hover:underline">همه تیکت‌ها ←</a>
                    </div>
                    <div class="mt-2 flex gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="نمایش تیکت‌ها">
                        <button type="button" @click="tab = 'awaiting'" :aria-pressed="tab === 'awaiting'" :class="tab === 'awaiting' ? 'bg-white text-[#0059DB] shadow-sm' : 'text-slate-600'" class="min-h-10 flex-1 rounded-md px-2 text-xs font-semibold">در انتظار پاسخ ({{ number_format($activity['awaiting_count']) }})</button>
                        <button type="button" @click="tab = 'recent'" :aria-pressed="tab === 'recent'" :class="tab === 'recent' ? 'bg-white text-[#0059DB] shadow-sm' : 'text-slate-600'" class="min-h-10 flex-1 rounded-md px-2 text-xs font-semibold">آخرین فعالیت</button>
                    </div>
                </div>
                @foreach (['awaiting' => $activity['awaiting_tickets'], 'recent' => $activity['recent_tickets']] as $tab => $tickets)
                    <div x-show="tab === '{{ $tab }}'" @if ($tab === 'recent') x-cloak @endif>
                        <p class="px-5 pt-3 text-xs text-slate-500">{{ $tab === 'awaiting' ? 'موارد جاری از همه بازه‌ها · فوری و قدیمی‌تر در ابتدا' : 'آخرین فعالیت در بازه انتخاب‌شده' }}</p>
                        <div class="divide-y divide-slate-100">
                            @forelse ($tickets as $ticket)
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block px-5 py-3 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                                    <div class="flex items-start justify-between gap-2"><h3 class="min-w-0 break-words text-sm font-semibold text-slate-900" dir="auto">{{ $ticket->subject }}</h3><x-admin.status-badge :value="$ticket->priority" :label="\App\Models\Ticket::priorities()[$ticket->priority]" /></div>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $ticket->customer?->name }} · {{ $ticket->assignee?->name ?: 'بدون مسئول' }}</p>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2"><x-admin.status-badge :value="$ticket->status" :label="\App\Models\Ticket::statuses()[$ticket->status]" /><time class="text-xs text-slate-500" datetime="{{ ($tab === 'awaiting' ? ($ticket->last_customer_reply_at ?? $ticket->created_at) : ($ticket->last_activity_at ?? $ticket->created_at))->toIso8601String() }}">{{ ($tab === 'awaiting' ? ($ticket->last_customer_reply_at ?? $ticket->created_at) : ($ticket->last_activity_at ?? $ticket->created_at))->diffForHumans() }}</time></div>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $tab === 'awaiting' ? 'تیکتی در انتظار پاسخ نیست.' : 'فعالیتی در این بازه ثبت نشده است.' }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
@endif
<div class="mt-6 grid items-start gap-5 {{ $activity['machines'] !== null && $activity['customers'] !== null ? 'xl:grid-cols-2' : 'grid-cols-1' }}">
    @if ($activity['machines'] !== null)
        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-vms-heading">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 id="recent-vms-heading" class="text-lg font-bold text-slate-950">آخرین ماشین‌های مجازی</h2><p class="mt-1 text-xs text-slate-500">ایجادشده در بازه انتخاب‌شده</p></div><a href="{{ route('admin.virtual-machines.index', $reportRange) }}" class="min-h-10 py-2 text-sm font-semibold text-[#0059DB] hover:underline">همه ماشین‌ها ←</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($activity['machines'] as $vm)
                    <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                        <div class="min-w-0"><h3 class="break-words text-sm font-semibold text-slate-900" dir="auto">{{ $vm->display_name }}</h3><p class="mt-1 text-xs text-slate-500">{{ $vm->customer?->name ?: 'بدون مشتری' }} · {{ $vm->created_at->diffForHumans() }}</p></div>
                        <div class="flex flex-wrap gap-2"><x-admin.status-badge :value="$vm->status" /><x-admin.status-badge :value="$vm->provisioning_status" /></div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">ماشین جدیدی در این بازه ایجاد نشده است.</p>
                @endforelse
            </div>
        </section>
    @endif
    @if ($activity['customers'] !== null)
        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-customers-heading">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 id="recent-customers-heading" class="text-lg font-bold text-slate-950">آخرین مشتریان</h2><p class="mt-1 text-xs text-slate-500">ثبت‌نام در بازه انتخاب‌شده</p></div><a href="{{ route('admin.customers.index', $reportRange) }}" class="min-h-10 py-2 text-sm font-semibold text-[#0059DB] hover:underline">همه مشتریان ←</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($activity['customers'] as $customer)
                    <a href="{{ route('admin.customers.show', $customer) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                        <div class="min-w-0"><h3 class="break-words text-sm font-semibold text-slate-900" dir="auto">{{ $customer->name }}</h3><p class="mt-1 text-xs text-slate-500">{{ $customer->created_at->diffForHumans() }} @if ($activity['machines_count'] !== null) · {{ number_format($customer->virtual_machines_count) }} ماشین @endif</p></div>
                        <x-admin.status-badge :value="$customer->status" />
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">مشتری جدیدی در این بازه ثبت‌نام نکرده است.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
