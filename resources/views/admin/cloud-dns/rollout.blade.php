<section id="cloud-dns-rollout" class="rounded-2xl border border-blue-100 bg-blue-50/30 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="font-black">انتشار تدریجی Cloud DNS</h2><p class="mt-2 text-sm leading-7 text-slate-600">دسترسی مشتریان را در دوره آزمایشی کنترل کنید. محدود کردن دسترسی، پاسخ‌دهی DNS دامنه‌های موجود یا همگام‌سازی آن‌ها را متوقف نمی‌کند.</p></div>
        <span class="shrink-0 rounded-full bg-white px-3 py-2 text-xs font-bold text-[#0069FF]">{{ ['disabled' => 'دسترسی مشتریان متوقف است', 'selected' => 'فقط مشتریان منتخب'][$rollout['mode']] }}</span>
    </div>
    @if($canManageRollout)
        <form method="POST" action="{{ route('admin.cloud-dns.rollout.update') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <input type="hidden" name="action" value="mode">
            <label class="flex-1 text-xs font-bold">حالت انتشار
                <select name="mode" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                    @foreach(['selected' => 'فقط مشتریان منتخب (آزمایشی)', 'disabled' => 'توقف دسترسی مشتریان'] as $value => $label)
                        <option value="{{ $value }}" @selected($rollout['mode'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white">ذخیره حالت انتشار</button>
        </form>
        <div class="mt-5 border-t border-blue-100 pt-5">
            <h3 class="text-sm font-black">مشتریان منتخب</h3>
            <p class="mt-2 text-xs leading-7 text-slate-500">در حالت آزمایشی، هم مشتری واردشده و هم مالک فضای کاری باید در این فهرست باشند. دسترسی‌های مشاهده و مدیریت DNS اعضای پروژه همچنان لازم است. این فهرست هنگام تغییر حالت انتشار حفظ می‌شود.</p>
            <form method="POST" action="{{ route('admin.cloud-dns.rollout.update') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf
                <input type="hidden" name="action" value="add">
                <label class="flex-1"><span class="sr-only">موبایل یا ایمیل مشتری</span><input name="identifier" value="{{ old('identifier') }}" required maxlength="255" dir="ltr" placeholder="شماره موبایل یا ایمیل مشتری" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"></label>
                <button class="rounded-xl border border-[#0069FF] bg-white px-5 py-3 text-sm font-bold text-[#0069FF]">افزودن مشتری</button>
            </form>
            <ul class="mt-4 divide-y divide-blue-100">
                @forelse($rolloutCustomers as $rolloutCustomer)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div><p class="text-sm font-bold">{{ $rolloutCustomer->name }}</p><p class="mt-1 font-mono text-xs text-slate-500" dir="ltr">{{ $rolloutCustomer->phone ?: $rolloutCustomer->email }}</p></div>
                        <form method="POST" action="{{ route('admin.cloud-dns.rollout.update') }}">
                            @csrf
                            <input type="hidden" name="action" value="remove"><input type="hidden" name="customer_id" value="{{ $rolloutCustomer->id }}">
                            <button class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-red-600" aria-label="حذف دسترسی {{ $rolloutCustomer->name }}">حذف از فهرست</button>
                        </form>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500">مشتری منتخبی وجود ندارد. در حالت آزمایشی، هیچ مشتری دسترسی نخواهد داشت.</li>
                @endforelse
            </ul>
        </div>
    @endif
</section>
