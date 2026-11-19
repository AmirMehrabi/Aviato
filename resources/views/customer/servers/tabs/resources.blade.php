<div class="space-y-5">
    @include('customer.servers.partials.resource-indicators', ['showUsage' => false])

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-black text-slate-950">پیکربندی</h3>
            <dl class="mt-4 divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['پلن', $server->bundle?->name ?: 'اختصاصی', 'auto'],
                    ['سیستم عامل', $server->cloudImage?->name ?: $server->os_template ?: '—', 'auto'],
                    ['موقعیت', $server->infrastructureLocation?->name ?: $server->proxmoxServer?->name ?: '—', 'auto'],
                    ['نشانی IP', $server->ip_address ?: 'در انتظار', $server->ip_address ? 'ltr' : 'rtl'],
                ] as [$label, $value, $direction])
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0">
                        <dt class="text-slate-600">{{ $label }}</dt>
                        <dd class="min-w-0 font-bold text-slate-950 [overflow-wrap:anywhere]">
                            <bdi dir="{{ $direction }}">{{ $value }}</bdi>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-black text-slate-950">دیسک‌های اضافه</h3>
            @if ($server->disks->isNotEmpty())
                <ul class="mt-4 space-y-3">
                    @foreach ($server->disks as $disk)
                        @php
                            [$diskStatusLabel, $diskStatusClasses] = match ($disk->status) {
                                'ready' => ['آماده', 'bg-emerald-50 text-emerald-700'],
                                'failed' => ['ناموفق', 'bg-red-50 text-red-700'],
                                default => ['در حال آماده‌سازی', 'bg-amber-50 text-amber-800'],
                            };
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                            <bdi class="min-w-0 font-bold text-slate-950 [overflow-wrap:anywhere]" dir="ltr">
                                {{ $disk->size_gb }} GB
                                @if ($disk->disk_device)
                                    · {{ $disk->disk_device }}
                                @endif
                            </bdi>
                            <span class="rounded-full px-2 py-1 text-xs font-bold {{ $diskStatusClasses }}">{{ $diskStatusLabel }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">دیسک اضافه‌ای ثبت نشده است.</p>
            @endif
        </section>
    </div>

    <a href="{{ route('customer.servers.tab', [$server, 'tab' => 'upgrade'], false) }}" class="inline-flex rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white hover:bg-[#0050D0]">ارتقای منابع</a>
</div>
