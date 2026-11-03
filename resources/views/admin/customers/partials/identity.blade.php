<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-semibold text-slate-950">جزئیات حساب</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div class="py-3"><p class="text-xs font-medium text-slate-500">ایمیل</p><p class="mt-2 font-semibold" dir="ltr">{{ $customer->email ?: '—' }}</p></div>
            <div class="py-3"><p class="text-xs font-medium text-slate-500">موبایل</p><p class="mt-2 font-semibold" dir="ltr">{{ $customer->phone ?: '—' }}</p></div>
            <div class="py-3"><p class="text-xs font-medium text-slate-500">آخرین تغییر</p><p class="mt-2 font-semibold">{{ \App\Support\Jalali::format($customer->updated_at) }}</p></div>
            <div class="py-3 md:col-span-3" x-data="{ revealed: false, copied: false, copyFailed: false, async copyCode(code) { try { await navigator.clipboard.writeText(code); this.copied = true; this.copyFailed = false; setTimeout(() => this.copied = false, 1800); } catch (error) { this.copied = false; this.copyFailed = true; } } }">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">کد ملی</p>
                        @if ($customer->national_code)
                            <p class="mt-2 font-mono text-lg font-semibold text-slate-900" dir="ltr" x-text="revealed ? @js($customer->national_code) : @js('••••••'.mb_substr($customer->national_code, -4))"></p>
                            <p class="mt-1 text-xs text-slate-500">{{ $customer->national_code_verified_at ? 'تایید شده در '.\App\Support\Jalali::format($customer->national_code_verified_at) : 'ثبت شده، اما تایید نشده' }}</p>
                        @elseif ($customer->national_code_hash || $customer->national_code_verified_at)
                            <p class="mt-2 font-medium text-slate-700">کد ملی تایید شده، اما مقدار آن برای نمایش در دسترس نیست.</p>
                            @if ($customer->national_code_verified_at)
                                <p class="mt-1 text-xs text-slate-500">تایید شده در {{ \App\Support\Jalali::format($customer->national_code_verified_at) }}</p>
                            @endif
                        @else
                            <p class="mt-2 font-medium text-slate-700">ثبت نشده</p>
                        @endif
                    </div>
                    @if ($customer->national_code)
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="revealed = !revealed" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" x-text="revealed ? 'پنهان کردن' : 'نمایش کد ملی'"></button>
                            <button type="button" @click="copyCode(@js($customer->national_code))" class="rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0050D0]" x-text="copied ? 'کپی شد' : 'کپی'"></button>
                        </div>
                    @endif
                </div>
                <p x-cloak x-show="copyFailed" class="mt-2 text-xs font-medium text-red-600">کپی انجام نشد؛ کد را پس از نمایش به‌صورت دستی کپی کنید.</p>
            </div>
        </div>
    </section>
