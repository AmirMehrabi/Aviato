        <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-xl font-black text-slate-950">پوشش هزینه‌ها</h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">این برآورد بر پایه وضعیت فعلی سرورها، دیسک‌ها و نسخه‌های پشتیبان است. تغییر منابع، وضعیت سرورها و مصرف شبکه می‌تواند مبلغ واقعی را تغییر دهد.</p>
                <dl class="mt-6 divide-y divide-slate-100 text-sm">
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="text-slate-600">برآورد این فضای کاری</dt><dd class="font-black text-slate-950">{{ $wallets->format($projectMonthlyEstimate) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="text-slate-600">برآورد تمام فضاهای کاری این کیف پول</dt><dd class="font-black text-slate-950">{{ $wallets->format($monthlyEstimate) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="text-slate-600">کارکرد ثبت‌نشده این فضا</dt><dd class="font-black text-slate-950">{{ $wallets->format($pendingUsage) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs leading-6 text-slate-500">شارژ پیشنهادی، کسری موجودی مؤثر نسبت به برآورد یک ماه تمام فضاهای کاری مشترک این کیف پول است.</p>
                @if ($canTopUp)
                    <a href="{{ route('customer.wallet.show', ['tab' => 'top-up'], false) }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white hover:bg-[#0050D0]">شارژ کیف پول</a>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-black text-slate-950">فعالیت این ماه</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-600">شارژها</dt><dd class="font-black text-emerald-700">{{ $wallets->format($monthlyCredits) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-600">کسرها</dt><dd class="font-black text-rose-600">{{ $wallets->format($monthlyCharges) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-600">وضعیت کیف پول</dt><dd class="font-black {{ $wallet->is_locked ? 'text-rose-600' : 'text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل شده' : 'فعال' }}</dd></div>
                </dl>
                <a href="{{ route('customer.wallet.show', ['tab' => 'transactions'], false) }}" class="mt-6 inline-flex text-sm font-black text-[#0069FF]">مشاهده تراکنش‌ها ←</a>
            </div>
        </section>
