    <div class="w-full bg-white border-b border-slate-200">
        <div class="mx-auto max-w-7xl px-4 pt-7 md:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4 pb-7">
                <div>
                    <h1 class="text-3xl font-black text-slate-950">کیف پول</h1>
                    <p class="mt-2 text-sm text-slate-500">موجودی مشترک فضاهای کاری و هزینه تخمینی خدمات</p>
                </div>
                @if ($canTopUp)
                    <a href="{{ route('customer.wallet.show', ['tab' => 'top-up'], false) }}" class="inline-flex min-h-11 items-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white hover:bg-[#0050D0]">افزایش موجودی</a>
                @endif
            </div>
            <dl class="grid gap-3 pb-7 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'موجودی کیف پول', 'value' => $wallets->format($wallet->balance), 'tone' => $wallet->balance < 0 ? 'text-rose-600' : 'text-slate-950'],
                    ['label' => 'موجودی پس از کارکرد ثبت‌نشده', 'value' => $wallets->format($effectiveBalance), 'tone' => $effectiveBalance < 0 ? 'text-rose-600' : 'text-slate-950'],
                    ['label' => 'برآورد ماهانه کل کیف پول', 'value' => $wallets->format($monthlyEstimate), 'tone' => 'text-slate-950'],
                    ['label' => 'شارژ پیشنهادی برای یک ماه', 'value' => $wallets->format($suggestedTopUp), 'tone' => 'text-[#0069FF]'],
                ] as $item)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt class="text-xs font-bold text-slate-500">{{ $item['label'] }}</dt>
                        <dd class="mt-2 break-words text-xl font-black {{ $item['tone'] }}">{{ $item['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
            <nav class="flex gap-8 overflow-x-auto" aria-label="بخش‌های کیف پول">
                @foreach ($tabItems as $item)
                    <a href="{{ route('customer.wallet.show', ['tab' => $item['key']], false) }}" @if($selectedTab === $item['key']) aria-current="page" @endif class="whitespace-nowrap border-b-2 py-4 text-sm {{ $selectedTab === $item['key'] ? 'border-[#0069FF] font-black text-[#0069FF]' : 'border-transparent font-bold text-slate-500 hover:text-slate-800' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </div>
