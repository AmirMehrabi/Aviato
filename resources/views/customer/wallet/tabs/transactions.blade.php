    <section
        x-data="walletTransactions({
            type: @js($selectedType),
            from: @js($transactionFilters['from'] ?? ''),
            to: @js($transactionFilters['to'] ?? ''),
            search: @js($transactionFilters['search'] ?? ''),
            html: @js(trim(view('customer.wallet._transactions', ['transactions' => $transactions, 'wallets' => $wallets])->render())),
            hasPages: @js($transactions->hasPages()),
        })"
        class="mt-6 min-w-0 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60"
    >
        <div class="border-b border-slate-200 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">تاریخچه تراکنش‌ها</h2>
                    <p class="mt-1 text-sm font-bold text-slate-500">تاریخ‌ها میلادی و به وقت تهران هستند. مانده هر تراکنش، موجودی ثبت‌شده پس از آن تراکنش است.</p>
                </div>
            </div>

            <div class="mt-4 grid items-end gap-3 sm:grid-cols-[1fr_auto_auto]">
                <div class="relative">
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="text"
                        x-model="search"
                        x-on:input.debounce.300ms="page = 1; load()"
                        placeholder="جستجو در توضیحات تراکنش..."
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white pr-11 pl-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10"
                    >
                </div>

                @foreach (['from' => 'از تاریخ (میلادی)', 'to' => 'تا تاریخ (میلادی)'] as $field => $label)
                    <label class="text-xs font-bold text-slate-500">
                        {{ $label }}
                        <input
                            type="date"
                            lang="en"
                            dir="ltr"
                            x-model="{{ $field }}"
                            x-on:change="page = 1; load()"
                            class="mt-1 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10 sm:w-44"
                        >
                    </label>
                @endforeach
            </div>

            <div class="mt-3 flex max-w-full flex-wrap items-center gap-2">
                @foreach (['all' => 'همه', 'credit' => 'شارژ', 'charge' => 'کارکرد', 'refund' => 'بازگشت', 'adjustment' => 'اصلاح', 'debit' => 'برداشت'] as $type => $label)
                    <button
                        type="button"
                        x-on:click="setType('{{ $type }}')"
                        :class="type === '{{ $type }}' ? 'bg-[#0069FF] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="rounded-full px-3 py-2 text-xs font-black transition"
                    >{{ $label }}</button>
                @endforeach
                <button
                    x-show="from || to || search !== '' || type !== 'all'"
                    x-cloak
                    type="button"
                    x-on:click="clearFilters()"
                    class="mr-auto rounded-full px-3 py-2 text-xs font-black text-rose-600 transition hover:bg-rose-50"
                >حذف فیلتر</button>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-b-[28px]">
            <div
                x-show="loading"
                x-cloak
                class="absolute inset-0 z-40 flex items-center justify-center bg-white/70"
            >
                <svg class="size-10 animate-spin text-[#0069FF]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"/>
                </svg>
            </div>

            <div x-html="html" x-on:click="handlePageClick">
                @include('customer.wallet._transactions', ['transactions' => $transactions, 'wallets' => $wallets])
            </div>
        </div>
    </section>

