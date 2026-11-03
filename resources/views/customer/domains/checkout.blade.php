@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('title', 'سفارش دامنه')
@section('header_title', $order->operation === 'renew' ? 'تمدید دامنه' : 'ثبت دامنه')
@section('header_subtitle', 'فضای کاری: '.$activeProject->name)
@php
    $activeNav = 'domains';
@endphp
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <a href="{{ route('customer.domains.index', [], false) }}" class="text-sm font-bold text-blue-600">→ بازگشت به دامنه‌ها</a>
    <p class="rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">فضای کاری: <strong>{{ $activeProject->name }}</strong> · پرداخت‌کننده: <strong>{{ $order->customer->name }}</strong></p>
    @if(session('status'))<p role="status" class="domain-notice">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="domain-error"><p class="font-black">لطفاً موارد زیر را بررسی کنید</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 dir="ltr" class="break-all text-2xl font-black text-slate-950">{{ $order->domain }}</h1><span class="domain-badge">{{ $order->operation === 'renew' ? 'تمدید' : 'ثبت' }} · {{ $order->period }} سال</span></div>
    @if(!in_array($order->status, ['draft', 'awaiting_payment']))
        <section class="domain-card text-center" x-data="{ state: @js($order->status), attempts: 0, timer: null, init() { if (@js($order->isProcessing())) { this.timer = setInterval(() => this.check(), 10000); } }, destroy() { clearInterval(this.timer); }, async check() { if (++this.attempts > 30) { clearInterval(this.timer); return; } try { const r = await fetch(@js(route('customer.domains.orders.show', $order, false)), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (r.ok) { const d = await r.json(); if (d.status !== this.state) { window.location.reload(); } } } catch (_) {} } }">
            <div class="mx-auto mb-5 flex size-16 items-center justify-center rounded-full {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' }} text-3xl" aria-hidden="true">{{ $order->status === 'completed' ? '✓' : ($order->isProcessing() ? '◷' : '—') }}</div>
            <h2 class="text-xl font-black" role="status">{{ $order->label() }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-8 text-slate-600">{{ $order->customer_message ?: ($order->isProcessing() ? 'پرداخت سفارش انجام شده و درخواست ثبت یا تمدید در حال انجام است. نیازی به پرداخت دوباره نیست. می‌توانید این صفحه را ببندید و بعداً از بخش دامنه‌ها نتیجه را ببینید.' : 'جزئیات سفارش در حساب شما محفوظ است.') }}</p>
            @if($order->payment?->type === \App\Models\Payment::TYPE_DOMAIN && $order->payment->isSuccessful())
                <p class="mt-4 text-sm leading-7 text-slate-600">پرداخت آنلاین: <strong>{{ $wallets->format($order->payment->amount, $order->payment->currency) }}</strong>@if($order->payment->provider_reference)<br>شماره پیگیری پرداخت: <bdi class="font-bold">{{ $order->payment->provider_reference }}</bdi>@endif</p>
            @endif
            @if($order->refund_transaction_id)<p class="mt-3 text-sm font-bold text-emerald-700">{{ $wallets->format($order->total, $order->currency) }} به کیف پول شما بازگشت. این مبلغ به حساب بانکی واریز نشده است.</p>@endif
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                @if($order->status === 'completed')
                    <a class="domain-primary" href="{{ route('customer.domains.show', $order->managedDomain, false) }}">مدیریت دامنه</a>
                    <a class="domain-secondary" href="{{ route('customer.domains.orders.invoice', $order, false) }}">مشاهده فاکتور</a>
                    <p class="w-full text-xs leading-7 text-slate-500">اگر سرویس‌دهنده ایمیل تأیید مالکیت فرستاده است، صندوق ورودی و پوشه هرزنامه خود را بررسی کنید.</p>
                @elseif($order->isProcessing())<a class="domain-secondary" href="{{ route('customer.domains.orders.show', $order, false) }}">بررسی دوباره وضعیت</a>
                @elseif($customer->canPurchaseDomains())<a class="domain-primary" href="{{ route('customer.domains.index', ['domain' => $order->domain], false) }}">جستجو و سفارش دوباره</a>@endif
            </div>
        </section>
    @else
        @if($order->customer_message)<p role="status" class="domain-notice">{{ $order->customer_message }}</p>@endif
        @if($order->payment && !$order->payment->isPending() && !$order->payment->isSuccessful())<p role="status" class="domain-notice">پرداخت قبلی تکمیل نشد. اطلاعات سفارش محفوظ است؛ روش پرداخت را انتخاب و دوباره تلاش کنید.</p>@endif
        <nav aria-label="مراحل خرید" class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
            @foreach(['owner' => '۱. مشخصات مالک', 'setup' => '۲. تنظیمات دامنه', 'review' => '۳. بررسی و پرداخت'] as $key => $label)
                @if($order->payment?->isPending())
                    <span class="rounded-xl border px-2 py-3 font-bold {{ $key === 'review' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500' }}" @if($key === 'review') aria-current="step" @endif>{{ $label }}</span>
                @else
                <a href="{{ route('customer.domains.orders.show', ['order' => $order, 'step' => $key], false) }}" class="rounded-xl border px-2 py-3 font-bold {{ $step === $key ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500' }}" @if($step === $key) aria-current="step" @endif>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <section class="domain-card">
                @if($step === 'owner')
                    <h2 class="domain-heading">دامنه به نام چه کسی ثبت شود؟</h2><p class="domain-help mt-2">اطلاعات واقعی مالک دامنه را وارد کنید. این مشخصات با اطلاعات پرداخت متفاوت است.</p>
                    <form method="POST" action="{{ route('customer.domains.orders.save', $order, false) }}" class="mt-6 space-y-5" x-data="{ saved: '', sameHandle: @js((bool) old('use_holder_for_all', true)) }">
                        @csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"> @method('PATCH')<input type="hidden" name="stage" value="owner">
                        @if($contacts->isNotEmpty())
                            <div><label for="contact_id" class="domain-label">استفاده از مشخصات ذخیره‌شده</label><select id="contact_id" name="contact_id" x-model="saved" class="domain-input"><option value="">ورود یا ویرایش مشخصات</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->label }}</option>@endforeach</select><p class="domain-help">با انتخاب یک پروفایل، مشخصات ذخیره‌شده در مرحله بعد نمایش داده می‌شود.</p></div>
                        @endif
                        <fieldset x-show="!saved" :disabled="!!saved" class="space-y-5">
                            @if($order->contact_mode === 'registry_handle')
                                @if($order->provider === 'irpower')
                                    <div class="domain-notice"><p class="font-black">ثبت دامنه با شناسه ایرنیک شما</p><p class="mt-2">شناسه‌ای مانند ab123-irnic وارد کنید. اگر شناسه ندارید، در <a href="https://www.nic.ir/" target="_blank" rel="noopener noreferrer" class="underline">nic.ir</a> بسازید. سپس در «رابطه‌های مجاز»، دسترسی نمایندگان را برای نقش‌های مورد نیاز فعال کنید. هنگام ادامه، دسترسی شناسه را بررسی می‌کنیم؛ هنوز پرداختی انجام نمی‌شود.</p></div>
                                    <div><label for="holder" class="domain-label">شناسه صاحب امتیاز ایرنیک</label><input id="holder" name="holder" dir="ltr" required maxlength="150" placeholder="ab123-irnic" value="{{ old('holder', $order->registrant['holder'] ?? '') }}" class="domain-input" autocomplete="off" autocapitalize="none" spellcheck="false"></div>
                                    <input type="hidden" name="use_holder_for_all" value="0"><label class="flex items-start gap-2 text-sm leading-7"><input type="checkbox" name="use_holder_for_all" value="1" x-model="sameHandle" @checked(old('use_holder_for_all', true)) class="mt-2 accent-blue-600">استفاده از همین شناسه برای رابط اداری، فنی و مالی</label>
                                    <div x-show="!sameHandle" class="space-y-4">@foreach(['admin'=>'شناسه رابط اداری', 'tech'=>'شناسه رابط فنی', 'billing'=>'شناسه رابط مالی'] as $field=>$label)<div><label for="{{ $field }}" class="domain-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" dir="ltr" :disabled="sameHandle" :required="!sameHandle" maxlength="150" value="{{ old($field, $order->registrant[$field] ?? '') }}" class="domain-input" autocapitalize="none" spellcheck="false"></div>@endforeach</div>
                                @else
                                    <p class="domain-notice">برای این پسوند، شناسه‌های معتبر ثبت‌شده در سامانه رجیستری لازم است. دسترسی نماینده برای ثبت دامنه را بررسی کنید.</p>
                                    @foreach(['holder'=>'شناسه صاحب امتیاز', 'admin'=>'شناسه رابط اداری', 'tech'=>'شناسه رابط فنی', 'billing'=>'شناسه رابط مالی'] as $field=>$label)<div><label for="{{ $field }}" class="domain-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" dir="ltr" required maxlength="150" value="{{ old($field, $order->registrant[$field] ?? '') }}" class="domain-input"></div>@endforeach
                                @endif
                            @else
                                @foreach(['name'=>'نام و نام خانوادگی مالک', 'email'=>'ایمیل مالک', 'organization'=>'نام سازمان (اختیاری)', 'country'=>'کشور', 'state'=>'استان', 'city'=>'شهر', 'address'=>'نشانی کامل', 'postCode'=>'کد پستی', 'phone'=>'شماره تماس'] as $field=>$label)
                                    @if($field === 'country')<div><label for="country" class="domain-label">کشور</label><select id="country" name="country" class="domain-input" required>@foreach($countries as $code=>$countryLabel)<option value="{{ $code }}" @selected(old('country', $order->registrant['country'] ?? 'IR') === $code)>{{ $countryLabel }}</option>@endforeach</select></div>@continue @endif
                                    <div><label for="{{ $field }}" class="domain-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" @if($field !== 'organization') required @endif maxlength="{{ $field === 'address' ? 500 : ($field === 'country' ? 2 : 150) }}" @if(in_array($field, ['email','country','phone','postCode'])) dir="ltr" @endif value="{{ old($field, $order->registrant[$field] ?? match($field) { 'name'=>$customer->name, 'email'=>$customer->email, 'country'=>'IR', default=>'' }) }}" class="domain-input" @if($field === 'country') autocapitalize="characters" @endif></div>
                                @endforeach
                                <p class="domain-help">ایمیل فعال وارد کنید؛ پیام تأیید مالکیت ممکن است به همین نشانی ارسال شود. نام و نشانی را مطابق الزامات رجیستری وارد کنید.</p>
                            @endif
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="save_contact" value="1" class="accent-blue-600"> ذخیره برای سفارش‌های بعدی</label>
                        </fieldset>
                        <button class="domain-primary w-full">ذخیره و ادامه به تنظیمات</button><p class="domain-help text-center">هنوز مبلغی پرداخت نمی‌کنید.</p>
                    </form>
                @elseif($step === 'setup')
                    <h2 class="domain-heading">دامنه را به کجا متصل کنیم؟</h2><p class="domain-help mt-2">حداقل دو نیم‌سرور ارائه‌شده توسط میزبان یا سرویس DNS خود را وارد کنید. بعد از ثبت هم می‌توانید آن‌ها را تغییر دهید.</p>
                    <form method="POST" action="{{ route('customer.domains.orders.save', $order, false) }}" class="mt-6 space-y-5">
                        @csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"> @method('PATCH')<input type="hidden" name="stage" value="setup">
                        @include('customer.domains._nameservers', ['initialNameservers' => $order->nameservers ?: \App\Models\AppSetting::getValue('domains.default_nameservers', []), 'maxNameservers' => $order->provider === 'irpower' ? 5 : 6])
                        <button class="domain-primary w-full">ذخیره و بررسی سفارش</button><a class="block text-center text-sm font-bold text-blue-600" href="{{ route('customer.domains.orders.show', ['order'=>$order,'step'=>'owner'], false) }}">بازگشت به مشخصات مالک</a>
                    </form>
                @else
                    <h2 class="domain-heading">همه‌چیز آماده است؟</h2>
                    @if(str_ends_with($order->domain, '.ir'))<p class="domain-notice mt-4">ثبت نهایی دامنه تابع تأیید ایرنیک است. نتیجه درخواست و تأیید تاریخ اعتبار در همین سفارش نمایش داده می‌شود؛ در زمان پیگیری، سفارش یا پرداخت دیگری لازم نیست.</p>@endif
                    <div class="mt-5 space-y-5 text-sm">
                        <div class="rounded-xl bg-slate-50 p-4"><div class="flex justify-between gap-3"><h3 class="font-black">مالک دامنه</h3>@if($order->operation === 'register' && !$order->payment?->isPending())<a href="{{ route('customer.domains.orders.show', ['order'=>$order,'step'=>'owner'], false) }}" class="text-blue-600">ویرایش</a>@endif</div><p class="mt-2">{{ $order->registrant['name'] ?? $order->registrant['holder'] ?? '' }}</p><p dir="ltr" class="mt-1 break-all text-right text-slate-500">{{ $order->registrant['email'] ?? '' }}</p><details class="mt-3"><summary class="cursor-pointer text-xs font-bold text-blue-600">مشاهده همه مشخصات مالک</summary><dl class="mt-3 space-y-2 text-xs leading-7">@foreach(['country'=>'کشور','state'=>'استان','city'=>'شهر','address'=>'نشانی','postCode'=>'کد پستی','phone'=>'تلفن','admin'=>'شناسه اداری','tech'=>'شناسه فنی','billing'=>'شناسه مالی'] as $field=>$label)@if(!empty($order->registrant[$field]))<div><dt class="inline text-slate-500">{{ $label }}: </dt><dd class="inline">{{ $order->registrant[$field] }}</dd></div>@endif @endforeach</dl></details></div>
                        <div class="rounded-xl bg-slate-50 p-4"><div class="flex justify-between"><h3 class="font-black">نیم‌سرورها</h3>@if($order->operation === 'register' && !$order->payment?->isPending())<a href="{{ route('customer.domains.orders.show', ['order'=>$order,'step'=>'setup'], false) }}" class="text-blue-600">ویرایش</a>@endif</div>@foreach($order->nameservers ?? [] as $ns)<p dir="ltr" class="mt-2 break-all text-right text-slate-500">{{ $ns }}</p>@endforeach</div>
                    </div>
                    @if($order->payment?->isPending())
                        <div class="domain-notice mt-6" role="status">
                            <h3 class="font-black">پرداخت شما هنوز تأیید نشده است</h3>
                            <p class="mt-2">اگر پرداخت را انجام داده‌اید، منتظر تأیید بانک بمانید و وضعیت را دوباره بررسی کنید. برای جلوگیری از پرداخت تکراری، پرداخت تازه‌ای ایجاد نمی‌شود.</p>
                            <p class="mt-2">مبلغ پرداخت جاری: <strong>{{ $wallets->format($order->payment->amount, $order->payment->currency) }}</strong></p>
                        </div>
                        <a class="domain-primary mt-5 w-full" href="{{ route('customer.wallet.payments.gateway.show', $order->payment, false) }}">ادامه پرداخت جاری</a>
                        <a class="domain-secondary mt-3 w-full" href="{{ route('customer.domains.orders.show', ['order' => $order, 'step' => 'review'], false) }}">بررسی وضعیت پرداخت</a>
                    @elseif($order->quoted_until->isPast())
                        <div class="domain-notice mt-5">اعتبار قیمت تمام شده است. مشخصات شما محفوظ است؛ برای ادامه قیمت را به‌روز کنید.</div><form method="POST" action="{{ route('customer.domains.orders.refresh', $order, false) }}" class="mt-4">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><button class="domain-primary w-full">دریافت قیمت جدید</button></form>
                    @else
                        @php
                            $selectedMethod = old('payment_method', $walletUsable ? 'wallet' : 'online');
                            if (! $walletUsable) $selectedMethod = 'online';
                            if (! in_array($selectedMethod, ['wallet', 'online'], true)) $selectedMethod = $walletUsable ? 'wallet' : 'online';
                            $selectedGateway = old('gateway', array_key_first($gateways));
                        @endphp
                        <form method="POST" action="{{ route('customer.domains.orders.pay', $order, false) }}" class="mt-6 space-y-5" x-data="{ submitting: false, method: @js($selectedMethod) }" @submit="submitting = true" @pageshow.window="submitting = false" :aria-busy="submitting">
                            @csrf
                            <input type="hidden" name="workspace" value="{{ $activeProject->uuid }}">
                            <fieldset class="space-y-3">
                                <legend class="mb-3 text-base font-black text-slate-950">چطور پرداخت می‌کنید؟</legend>
                                <label class="flex items-start gap-3 rounded-2xl border p-4 text-sm leading-7 focus-within:ring-2 focus-within:ring-blue-600 focus-within:ring-offset-2 {{ !$walletUsable ? 'border-slate-200 bg-slate-50' : 'cursor-pointer border-slate-200' }}" :class="method === 'wallet' ? 'border-blue-500 bg-blue-50' : ''">
                                    <input type="radio" name="payment_method" value="wallet" x-model="method" @checked($selectedMethod === 'wallet') @disabled(!$walletUsable) aria-describedby="wallet-payment-help" class="mt-2 size-4 shrink-0 accent-blue-600">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-black text-slate-900">پرداخت از کیف پول</span>
                                        <span id="wallet-payment-help" class="mt-1 block text-slate-600">موجودی قابل استفاده: {{ $wallets->format($available, $order->currency) }}</span>
                                        @if($walletUsable)
                                            <span class="mt-1 block text-xs text-slate-600">موجودی پس از پرداخت: {{ $wallets->format($available - $order->total, $order->currency) }}</span>
                                        @else
                                            <span class="mt-1 block text-xs font-bold text-slate-600">{{ $shortfall ? 'موجودی کیف پول کافی نیست. می‌توانید مبلغ کامل را آنلاین پرداخت کنید.' : 'پرداخت از کیف پول این حساب در دسترس نیست.' }}</span>
                                        @endif
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-7 focus-within:ring-2 focus-within:ring-blue-600 focus-within:ring-offset-2" :class="method === 'online' ? 'border-blue-500 bg-blue-50' : ''">
                                    <input type="radio" name="payment_method" value="online" x-model="method" @checked($selectedMethod === 'online') aria-describedby="online-payment-help" class="mt-2 size-4 shrink-0 accent-blue-600">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-black text-slate-900">پرداخت آنلاین</span>
                                        <span id="online-payment-help" class="mt-1 block text-slate-600">پرداخت مبلغ کامل سفارش از طریق درگاه بانکی؛ موجودی کیف پول شما تغییری نمی‌کند.</span>
                                        <span class="mt-1 block font-bold text-blue-700">{{ $wallets->format($order->total, $order->currency) }}</span>
                                    </span>
                                </label>
                            </fieldset>
                            <div x-show="method === 'online'">
                                @if(count($gateways) > 1)
                                    <label for="gateway" class="domain-label">درگاه پرداخت</label>
                                    <select id="gateway" name="gateway" class="domain-input" :disabled="method !== 'online'" :required="method === 'online'">
                                        @foreach($gateways as $key => $label)<option value="{{ $key }}" @selected($selectedGateway === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                @elseif($gateways)
                                    <input type="hidden" name="gateway" value="{{ array_key_first($gateways) }}" :disabled="method !== 'online'">
                                    <p class="domain-help">پرداخت امن از طریق {{ reset($gateways) }}؛ پس از پرداخت به همین سفارش برمی‌گردید.</p>
                                @else
                                    <p role="status" class="domain-error">پرداخت آنلاین فعلاً در دسترس نیست. اطلاعات سفارش محفوظ است؛ کمی بعد دوباره تلاش کنید{{ $walletUsable ? ' یا از کیف پول پرداخت کنید' : '' }}.</p>
                                @endif
                            </div>
                            <label class="flex items-start gap-3 text-sm leading-7"><input class="mt-2 size-4 shrink-0 accent-blue-600" type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>نام دامنه، اطلاعات مالک، دوره و مبلغ را بررسی کردم و انجام سفارش را تأیید می‌کنم. آزاد بودن دامنه تا تأیید نهایی تضمین نمی‌شود. تمدید خودکار اکنون فعال نمی‌شود.</span></label>
                            <button type="submit" :disabled="submitting || (method === 'online' && @js(!$gateways))" class="domain-primary w-full disabled:cursor-not-allowed disabled:opacity-60">
                                <span x-text="method === 'wallet' ? 'پرداخت از کیف پول' : 'ادامه به درگاه پرداخت'">{{ $selectedMethod === 'wallet' ? 'پرداخت از کیف پول' : 'ادامه به درگاه پرداخت' }}</span><span x-show="submitting" x-cloak role="status" class="ms-2 text-xs">در حال ادامه…</span>
                            </button>
                            <p class="domain-help">اگر ثبت یا تمدید انجام نشود، مبلغ پرداخت‌شده به کیف پول شما بازمی‌گردد. بازپرداخت به حساب بانکی فرایند جداگانه دارد.</p>
                        </form>
                        <form method="POST" action="{{ route('customer.domains.orders.refresh', $order, false) }}" class="mt-4">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><button class="text-xs font-bold text-blue-600">بررسی دوباره قیمت</button></form>
                    @endif
                @endif
            </section>
            <aside class="domain-card {{ $step === 'review' ? 'order-first lg:order-last' : '' }} lg:sticky lg:top-6">
                <h2 class="domain-heading">خلاصه سفارش</h2><dl class="mt-5 space-y-4 text-sm"><div class="flex justify-between gap-2"><dt class="text-slate-500">دوره</dt><dd class="font-bold">{{ $order->period }} سال</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-500">مبلغ پایه</dt><dd class="font-bold">{{ $wallets->format($order->subtotal, $order->currency) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-500">مالیات و عوارض</dt><dd>{{ $wallets->format($order->tax_amount, $order->currency) }}</dd></div><div class="flex justify-between gap-2 border-t border-slate-100 pt-4"><dt class="font-black">جمع کل</dt><dd class="font-black text-blue-600">{{ $wallets->format($order->total, $order->currency) }}</dd></div></dl>
                @if($renewalPrice)<p class="domain-help mt-5">قیمت فعلی تمدید یک‌ساله: {{ $renewalPrice }}. قیمت تمدید در زمان تمدید محاسبه می‌شود.</p>@endif
                <p class="domain-help mt-3">قیمت تا {{ \App\Support\Jalali::format($order->quoted_until, 'H:i') }} معتبر است. پیشرفت شما با هر مرحله ذخیره می‌شود.</p>
                <form method="POST" action="{{ route('customer.domains.orders.cancel', $order, false) }}" class="mt-5">@csrf<input type="hidden" name="workspace" value="{{ $activeProject->uuid }}"><button class="text-xs text-slate-500 underline">لغو این سفارش</button></form>
            </aside>
        </div>
    @endif
    <p class="text-center text-xs text-slate-400">شماره سفارش: <span dir="ltr">DOM-{{ str_pad($order->id, 8, '0', STR_PAD_LEFT) }}</span></p>
</div>
@endsection
