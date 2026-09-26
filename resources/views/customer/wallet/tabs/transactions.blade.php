    <section
        x-data="walletTransactions({
            type: @js($selectedType),
            html: @js(trim(view('customer.wallet._transactions', ['transactions' => $transactions, 'wallets' => $wallets])->render())),
            hasPages: @js($transactions->hasPages()),
        })"
        class="mt-6 min-w-0 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60"
    >
        <div class="border-b border-slate-200 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">تاریخچه تراکنش‌ها</h2>
                    <p class="mt-1 text-sm font-bold text-slate-500">تمام ورودی‌ها و خروجی‌های کیف پول با مانده بعد از تراکنش ثبت می‌شوند.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
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

                <div class="relative">
                    <input
                        type="text"
                        x-model="from"
                        x-on:click.stop="openPicker('from')"
                        placeholder="از تاریخ (مثلا 1403/01/01)"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10 sm:w-40"
                    >
                    <div
                        x-show="pickerTarget === 'from'"
                        x-on:click.outside="closePicker()"
                        x-cloak
                        class="absolute right-0 top-full z-50 mt-1 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-200/60"
                    >
                        <div class="flex items-center justify-between">
                            <button type="button" x-on:click="prevCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>
                            </button>
                            <span class="text-sm font-black text-slate-900" x-text="calendarMonthName + ' ' + pickerYear"></span>
                            <button type="button" x-on:click="nextCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-black text-slate-400">
                            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <template x-for="(week, wi) in calendarWeeks" :key="wi">
                            <div class="mt-1 grid grid-cols-7 gap-1 text-center">
                                <template x-for="(day, di) in week" :key="di">
                                    <div>
                                        <button
                                            type="button"
                                            x-show="day !== null"
                                            x-on:click="selectDate(day)"
                                            :class="Number(from?.split('/')[2]) === day && pickerTarget === 'from' && from?.startsWith(pickerYear+'/'+String(pickerMonth).padStart(2,'0')) ? 'bg-[#0069FF] text-white' : 'text-slate-700 hover:bg-slate-100'"
                                            class="w-full rounded-lg p-1.5 text-xs font-bold transition"
                                            x-text="day"
                                        ></button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        x-model="to"
                        x-on:click.stop="openPicker('to')"
                        placeholder="تا تاریخ (مثلا 1403/01/01)"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10 sm:w-40"
                    >
                    <div
                        x-show="pickerTarget === 'to'"
                        x-on:click.outside="closePicker()"
                        x-cloak
                        class="absolute right-0 top-full z-50 mt-1 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-200/60"
                    >
                        <div class="flex items-center justify-between">
                            <button type="button" x-on:click="prevCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>
                            </button>
                            <span class="text-sm font-black text-slate-900" x-text="calendarMonthName + ' ' + pickerYear"></span>
                            <button type="button" x-on:click="nextCalendarMonth()" class="grid size-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-black text-slate-400">
                            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <template x-for="(week, wi) in calendarWeeks" :key="wi">
                            <div class="mt-1 grid grid-cols-7 gap-1 text-center">
                                <template x-for="(day, di) in week" :key="di">
                                    <div>
                                        <button
                                            type="button"
                                            x-show="day !== null"
                                            x-on:click="selectDate(day)"
                                            :class="Number(to?.split('/')[2]) === day && pickerTarget === 'to' && to?.startsWith(pickerYear+'/'+String(pickerMonth).padStart(2,'0')) ? 'bg-[#0069FF] text-white' : 'text-slate-700 hover:bg-slate-100'"
                                            class="w-full rounded-lg p-1.5 text-xs font-bold transition"
                                            x-text="day"
                                        ></button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
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

