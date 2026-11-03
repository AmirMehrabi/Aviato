@adminRoute('admin.colocation.customer')
    <section class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <div><h2 class="font-semibold">کولوکیشن</h2><p class="mt-1 text-sm text-slate-500">پلن‌ها و سرویس‌های کولوکیشن این مشتری</p></div>
        <a href="{{ route('admin.colocation.customer', $customer) }}" class="rounded-lg border border-blue-200 px-4 py-3 text-sm font-semibold text-blue-700">مشاهده سرویس‌های کولوکیشن</a>
    </section>
@endadminRoute
@adminAbility('virtual-machines.read')
    <section class="mt-6 min-w-0 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">ماشین‌های مجازی مشتری</h2>
            @if(auth('admin')->user()->is_active)
                @adminRoute('admin.virtual-machines.create')
                    <a href="{{ route('admin.virtual-machines.create', ['customer_id' => $customer->id]) }}" class="rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white">ساخت ماشین مجازی</a>
                @endadminRoute
            @endif
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[760px] text-start text-sm">
                <caption class="sr-only">سرویس‌ها، وضعیت و هزینه تخمینی ماهانه</caption>
                <thead><tr class="border-b border-slate-200 text-start text-xs text-slate-500">
                    <th scope="col" class="px-3 py-3 text-start">سرویس</th><th scope="col" class="px-3 py-3 text-start">وضعیت</th><th scope="col" class="px-3 py-3 text-start">منابع</th><th scope="col" class="px-3 py-3 text-start">نود</th>
                    @adminAbility('billing.read')<th scope="col" class="px-3 py-3 text-start">هزینه تخمینی ماهانه</th>@endadminAbility
                    <th scope="col" class="px-3 py-3 text-start">محدودیت کیف پول</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($virtualMachines as $vm)
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-3 py-4">
                                <p class="font-semibold"><bdi>{{ $vm->display_name }}</bdi></p>
                                <p class="mt-1 text-xs text-slate-500"><bdi>{{ $vm->name }}</bdi></p>
                                @adminRoute('admin.virtual-machines.show')<a href="{{ route('admin.virtual-machines.show', $vm) }}" class="mt-2 inline-block py-1 text-blue-700">جزئیات سرویس</a>@endadminRoute
                            </td>
                            <td class="px-3 py-4"><span class="inline-block rounded-full px-2 py-1 text-xs {{ $vm->status === 'running' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">{{ \App\Support\AdminUi::status($vm->status) }}</span></td>
                            <td class="px-3 py-4 text-xs leading-6"><p><bdi>{{ $vm->cpu_cores }} vCPU</bdi></p><p>حافظه: <bdi>{{ $vm->ram_gb }} GB</bdi></p><p>دیسک: <bdi>{{ $vm->disk_gb }} GB</bdi></p></td>
                            <td class="px-3 py-4"><bdi>{{ $vm->node ?: '—' }}</bdi></td>
                            @adminAbility('billing.read')<td class="px-3 py-4 font-semibold">{{ $wallets->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }}</td>@endadminAbility
                            <td class="max-w-xs px-3 py-4 text-xs leading-6">
                                @if(data_get($vm->wallet_restriction, 'last_error'))<p class="break-words text-red-700">خطای محدودیت کیف پول: {{ data_get($vm->wallet_restriction, 'last_error') }}</p>
                                @elseif(data_get($vm->wallet_restriction, 'wallet_stopped'))<p class="text-red-700">خاموش به‌دلیل بدهی کیف پول</p>
                                @elseif(data_get($vm->wallet_restriction, 'network_frozen_at'))<p class="text-amber-700">شبکه قطع به‌دلیل کیف پول</p>
                                @else<span class="text-slate-500">بدون محدودیت</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth('admin')->user()->allows('billing.read') ? 6 : 5 }}" class="py-10 text-center text-slate-500">هنوز ماشین مجازی برای این مشتری ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endadminAbility
