@adminAbility('billing.read')

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">کیف پول مشتری</h2>
                    <p class="mt-1 text-sm text-slate-500">افزایش و کسر اعتبار را همراه با توضیح ثبت کنید.</p>
                </div>
                <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $wallet->is_locked ? 'bg-red-50 text-red-700' : 'bg-[#EBF3FF] text-[#0069FF]' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
            </div>
            <p class="mt-6 break-words text-2xl font-semibold {{ $wallet->balance < 0 ? 'text-red-600' : 'text-[#0069FF]' }}">{{ $wallets->format($wallet->balance) }}</p>
            @if(auth('admin')->user()->is_active)
@adminRoute('admin.customers.wallet-transactions.store')
<details class="mt-5" @if($errors->hasAny(['type', 'amount_toman', 'amount', 'wallet', 'description', 'allow_negative'])) open @endif>
<summary class="cursor-pointer rounded-lg bg-blue-50 px-4 py-3 font-semibold text-blue-700">ثبت تراکنش</summary>
<form method="POST" action="{{ route('admin.customers.wallet-transactions.store', $customer) }}" class="mt-6 grid gap-3 md:grid-cols-2" x-data="{ type: @js(old('type', 'credit')), amount: @js(old('amount_toman', '')), balance: {{ $wallet->balance / (\App\Models\AppSetting::currency() === 'IRR' ? 10 : 1) }}, formatAmount(value) { return value.replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)).replace(/[٠-٩]/g, digit => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)).replace(/[,٬]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ','); } }">
                @csrf
                <label class="grid gap-2 text-sm font-medium">نوع تراکنش
                <select name="type" x-model="type" class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium focus:border-[#0069FF] focus:ring-2 focus:ring-blue-500">
                    <option value="credit">افزایش اعتبار</option>
                    <option value="debit">کسر اعتبار</option>
                </select>
                </label>
                <div class="grid content-start gap-2">
                    <label for="wallet-amount-toman" class="text-sm font-medium">مبلغ (تومان)</label>
                    <div class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 focus-within:ring-2 focus-within:ring-[#0069FF]">
                        <input id="wallet-amount-toman" name="amount_toman" type="text" inputmode="numeric" required value="{{ old('amount_toman') }}" x-init="amount = formatAmount(amount)" :value="amount" @input="amount = formatAmount($event.target.value); $event.target.value = amount" aria-describedby="wallet-amount-error" @error('amount_toman') aria-invalid="true" @enderror placeholder="۱۰۰٬۰۰۰" dir="ltr" class="min-w-0 w-full border-0 bg-transparent py-3 text-sm focus:ring-2 focus:ring-blue-500">
                        <span class="shrink-0 text-sm text-slate-500">تومان</span>
                    </div>
                    <p id="wallet-amount-error" class="text-xs text-red-700">@error('amount_toman'){{ $message }}@enderror</p>
                </div>
                <p class="text-sm font-medium text-slate-700 md:col-span-2" aria-live="polite" x-text="amount ? amount + ' تومان ' + (type === 'credit' ? 'به کیف پول اضافه می‌شود.' : 'از کیف پول کسر می‌شود.') : ''"></p>
                <p class="text-sm text-slate-600 md:col-span-2" aria-live="polite" x-show="amount" x-text="'موجودی پس از تراکنش: ' + (balance + (type === 'credit' ? 1 : -1) * Number(amount.replace(/,/g, ''))).toLocaleString('fa-IR') + ' تومان'"></p>
<label class="grid gap-2 text-sm md:col-span-2">توضیح تراکنش<input name="description" value="{{ old('description') }}" required class="rounded-lg border border-slate-200 px-4 py-3"></label>
                <label x-show="type === 'debit'" class="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-3 text-xs font-medium text-slate-600 md:col-span-2">
                    <input type="checkbox" name="allow_negative" value="1" :disabled="type !== 'debit'" @checked(old('allow_negative')) class="size-4 rounded border-slate-300 text-[#0069FF]">
                    اجازه منفی شدن موجودی برای این کسر اعتبار
                </label>
                <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-semibold text-white md:col-span-2">ثبت تراکنش</button>
            </form>
</details>
@endadminRoute


@adminRoute('admin.customers.wallet-lock.update')
<details class="mt-5" @error('lock_reason') open @enderror><summary class="cursor-pointer px-1 py-3 font-semibold text-slate-700">مدیریت قفل کیف پول</summary>
<form method="POST" action="{{ route('admin.customers.wallet-lock.update', $customer) }}" class="mt-4 rounded-xl border border-dashed border-slate-300 p-4">
                @csrf @method('PATCH')
                <input type="hidden" name="is_locked" value="{{ $wallet->is_locked ? 0 : 1 }}">
                @unless($wallet->is_locked)
                    <label class="grid gap-2 text-sm">دلیل قفل کردن کیف پول<input name="lock_reason" value="{{ old('lock_reason') }}" class="mb-3 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-[#0069FF] focus:ring-2 focus:ring-blue-500"></label>
                @endunless
                <button class="rounded-lg {{ $wallet->is_locked ? 'bg-[#0069FF]' : 'bg-red-600' }} px-5 py-3 text-sm font-semibold text-white">{{ $wallet->is_locked ? 'باز کردن کیف پول' : 'قفل کردن کیف پول' }}</button>
            </form>
</details>
@endadminRoute
@endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950">آخرین تراکنش‌های کیف پول</h2>
            <form method="GET" action="{{ route('admin.customers.show', $customer) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                <input type="hidden" name="tab" value="billing">
                <label class="grid gap-2 text-sm">نوع تراکنش<select name="transaction_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">همه انواع</option>@foreach(['credit'=>'افزایش اعتبار','debit'=>'کسر اعتبار','charge'=>'هزینه','refund'=>'بازپرداخت','adjustment'=>'اصلاح'] as $type=>$label)<option value="{{ $type }}" @selected(($transactionFilters['transaction_type'] ?? '') === $type)>{{ $label }}</option>@endforeach</select></label>
                <label class="grid gap-2 text-sm">از تاریخ (میلادی)<input type="date" name="transaction_from" value="{{ $transactionFilters['transaction_from'] ?? '' }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
                <label class="grid gap-2 text-sm">تا تاریخ (میلادی)<input type="date" name="transaction_to" value="{{ $transactionFilters['transaction_to'] ?? '' }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
                <div class="grid gap-2 text-sm"><label for="transaction-search">جست‌وجو در توضیحات</label><div class="flex gap-2"><input id="transaction-search" name="transaction_search" value="{{ $transactionFilters['transaction_search'] ?? '' }}" placeholder="جست‌وجو در توضیحات" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm"><button class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-medium text-white">اعمال</button></div></div>
<a href="{{ route('admin.customers.show', [$customer, 'tab' => 'billing']) }}" class="py-2 text-sm text-blue-700">پاک کردن فیلترها</a>
            </form>
            <div class="mt-5 space-y-3">
                @forelse($walletTransactions as $transaction)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-950">{{ $transaction->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ \App\Support\Jalali::format($transaction->created_at) }} · {{ $transaction->createdBy?->name ?: 'سیستم' }}</p>
                            </div>
                            <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $transaction->amount >= 0 ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-red-50 text-red-700' }}">{{ $wallets->format($transaction->amount) }}</span>
                        </div>
                        <p class="mt-3 text-left text-xs font-medium text-slate-500">موجودی پس از تراکنش: {{ $wallets->format($transaction->balance_after) }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">{{ count(array_filter($transactionFilters)) ? 'تراکنشی با این فیلترها پیدا نشد.' : 'هنوز تراکنشی ثبت نشده است.' }}</div>
                @endforelse
            </div>
            @if($walletTransactions instanceof \Illuminate\Contracts\Pagination\Paginator || $walletTransactions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="mt-4">{{ $walletTransactions->links() }}</div>
            @endif
        </div>
    </section>



@endadminAbility
