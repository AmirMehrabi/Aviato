@extends('layouts.marketing')

@section('title', 'مستندات API | آویاتو')
@section('body_class', 'bg-[#F5F8FD]')

@section('content')
@php
    $baseUrl = url('/api/v1');
    $walletCurl = 'curl -X GET "'.$baseUrl.'/projects/YOUR_PROJECT_UUID/wallet" -H "Accept: application/json" -H "Authorization: Bearer YOUR_API_KEY"';
    $transactionsCurl = 'curl -X GET "'.$baseUrl.'/projects/YOUR_PROJECT_UUID/wallet/transactions?per_page=25" -H "Accept: application/json" -H "Authorization: Bearer YOUR_API_KEY"';
    $transactionCurl = 'curl -X GET "'.$baseUrl.'/projects/YOUR_PROJECT_UUID/wallet/transactions/TRANSACTION_ID" -H "Accept: application/json" -H "Authorization: Bearer YOUR_API_KEY"';
@endphp

<div x-data="{
    copied: null,
    mobileNav: false,
    activeSection: 'quick-start',
    tabs: { wallet: 'curl', transactions: 'curl', transaction: 'curl' },
    copy(value, key) {
        navigator.clipboard?.writeText(value);
        this.copied = key;
        setTimeout(() => this.copied = null, 1600);
    }
}" x-init="
    const sections = [...document.querySelectorAll('main section[id]')];
    const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) activeSection = visible[0].target.id;
    }, { rootMargin: '-18% 0px -68% 0px', threshold: [0, 0.1] });
    sections.forEach(section => observer.observe(section));
" class="px-4 pb-24 pt-24 md:px-8 lg:px-10">
    <div class="mx-auto max-w-7xl">
        <header class="relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_20px_60px_-35px_rgba(7,27,58,.35)]">
            <div class="absolute inset-y-0 left-0 hidden w-1/3 bg-[radial-gradient(circle_at_30%_45%,rgba(0,105,255,.16),transparent_65%)] lg:block"></div>
            <div class="relative grid gap-8 px-6 py-8 md:px-10 md:py-10 lg:grid-cols-[1fr_19rem] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3 text-xs font-black">
                        <span class="rounded-full bg-[#EAF2FF] px-3 py-1.5 text-[#0069FF]">AVIATO API · V1</span>
                        <span class="inline-flex items-center gap-1.5 text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>سرویس پایدار است</span>
                    </div>
                    <h1 class="mt-5 max-w-3xl text-3xl font-black leading-[1.35] tracking-tight text-slate-950 md:text-5xl">مستندات API، برای ساختن سریع‌تر</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-8 text-slate-600 md:text-base">موجودی و تراکنش‌های فضای کاری خود را با چند درخواست ساده بخوانید. این راهنما از ساخت کلید تا مدیریت خطا، مسیر کوتاه و روشنی برای شروع در اختیار شما می‌گذارد.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#quick-start" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">شروع سریع</a>
                        <a href="{{ route('customer.login') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:text-[#0069FF]">ورود و ساخت کلید</a>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3"><span class="font-bold text-slate-500">آدرس پایه</span><span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black text-emerald-700">READ ONLY</span></div>
                    <code class="mt-3 block break-all font-mono text-xs leading-6 text-slate-800" dir="ltr">{{ $baseUrl }}</code>
                    <p class="mt-3 text-xs leading-6 text-slate-500">تمام پاسخ‌ها با فرمت JSON و یک Request ID برمی‌گردند.</p>
                </div>
            </div>
        </header>

        <button type="button" @click="mobileNav = !mobileNav" class="mt-6 flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-800 lg:hidden" :aria-expanded="mobileNav.toString()">
            <span>فهرست مستندات</span><svg class="size-4 transition" :class="mobileNav ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <nav x-cloak x-show="mobileNav" class="mt-2 rounded-xl border border-slate-200 bg-white p-3 lg:hidden" aria-label="فهرست مستندات">
            <div class="grid gap-1 sm:grid-cols-2">
                <a @click="mobileNav = false; activeSection = 'quick-start'" href="#quick-start" :class="activeSection === 'quick-start' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">شروع سریع</a>
                <a @click="mobileNav = false; activeSection = 'authentication'" href="#authentication" :class="activeSection === 'authentication' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">احراز هویت</a>
                <a @click="mobileNav = false; activeSection = 'wallet'" href="#wallet" :class="activeSection === 'wallet' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">کیف پول</a>
                <a @click="mobileNav = false; activeSection = 'transactions'" href="#transactions" :class="activeSection === 'transactions' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">تراکنش‌ها</a>
                <a @click="mobileNav = false; activeSection = 'transaction-detail'" href="#transaction-detail" :class="activeSection === 'transaction-detail' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">جزئیات تراکنش</a>
                <a @click="mobileNav = false; activeSection = 'errors'" href="#errors" :class="activeSection === 'errors' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">خطاها</a>
                <a @click="mobileNav = false; activeSection = 'security'" href="#security" :class="activeSection === 'security' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-[#F3F7FF] hover:text-[#0069FF]">نکات امنیتی</a>
            </div>
        </nav>

        <div class="mt-8 grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="hidden lg:block">
                <nav class="sticky top-24 rounded-2xl border border-slate-200 bg-white p-3" aria-label="فهرست مستندات">
                    <p class="px-3 pb-2 pt-1 text-[10px] font-black tracking-[.16em] text-slate-400">ON THIS PAGE</p>
                    <a @click="activeSection = 'quick-start'" href="#quick-start" :class="activeSection === 'quick-start' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="mb-1 block rounded-lg px-3 py-2.5 text-sm font-black transition hover:bg-slate-50 hover:text-[#0069FF]">شروع سریع</a>
                    <p class="px-3 pb-1 pt-4 text-[10px] font-black tracking-[.16em] text-slate-400">GUIDE</p>
                    <a @click="activeSection = 'authentication'" href="#authentication" :class="activeSection === 'authentication' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">احراز هویت</a>
                    <p class="px-3 pb-1 pt-4 text-[10px] font-black tracking-[.16em] text-slate-400">ENDPOINTS</p>
                    <a @click="activeSection = 'wallet'" href="#wallet" :class="activeSection === 'wallet' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">کیف پول</a>
                    <a @click="activeSection = 'transactions'" href="#transactions" :class="activeSection === 'transactions' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">تراکنش‌ها</a>
                    <a @click="activeSection = 'transaction-detail'" href="#transaction-detail" :class="activeSection === 'transaction-detail' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">جزئیات تراکنش</a>
                    <p class="px-3 pb-1 pt-4 text-[10px] font-black tracking-[.16em] text-slate-400">REFERENCE</p>
                    <a @click="activeSection = 'errors'" href="#errors" :class="activeSection === 'errors' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">خطاها</a>
                    <a @click="activeSection = 'security'" href="#security" :class="activeSection === 'security' ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-600'" class="block rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-slate-50 hover:text-[#0069FF]">نکات امنیتی</a>
                </nav>
            </aside>

            <main class="min-w-0 space-y-8">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-8 text-amber-950"><strong>قبل از ارسال درخواست:</strong> مقدار <code dir="ltr">YOUR_PROJECT_UUID</code> فقط یک placeholder است. UUID واقعی پروژه را از آدرس یا صفحه جزئیات همان پروژه در پنل مشتری پیدا و جایگزین کنید.</div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-8 text-slate-600">
                    <h2 class="text-lg font-black text-slate-950">Wallet endpoints</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <a href="#wallet" class="rounded-xl bg-slate-50 p-4"><strong class="block text-slate-950">Get remaining balance</strong><code dir="ltr" class="text-xs">/wallet</code></a>
                        <a href="#transactions" class="rounded-xl bg-slate-50 p-4"><strong class="block text-slate-950">List transactions</strong><code dir="ltr" class="text-xs">/wallet/transactions</code></a>
                        <a href="#transaction-detail" class="rounded-xl bg-slate-50 p-4"><strong class="block text-slate-950">Get one transaction</strong><code dir="ltr" class="text-xs">/wallet/transactions/{transaction}</code></a>
                    </div>
                    <p class="mt-4">خطاهای رایج: UUID نامعتبر یا placeholder و تراکنش ناموجود یا خارج از scope با ۴۰۴، پروژه معتبر بدون دسترسی مالی با ۴۰۳، و فیلتر نامعتبر با ۴۲۲ پاسخ داده می‌شود.</p>
                </div>
                <section id="quick-start" class="scroll-mt-24 rounded-2xl border border-[#B8D6FF] bg-[#F4F8FF] p-6 md:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">QUICK START</p><h2 class="mt-2 text-2xl font-black text-slate-950">در سه مرحله شروع کنید</h2></div><span class="rounded-full border border-[#CFE0FF] bg-white px-3 py-1.5 text-xs font-bold text-slate-600">زمان لازم: کمتر از ۲ دقیقه</span></div>
                    <div class="mt-6 grid gap-3 md:grid-cols-3">
                        <div class="rounded-xl border border-white bg-white p-4"><span class="flex size-7 items-center justify-center rounded-lg bg-[#EAF2FF] text-xs font-black text-[#0069FF]">۱</span><h3 class="mt-4 text-sm font-black text-slate-900">کلید بسازید</h3><p class="mt-2 text-xs leading-6 text-slate-500">از پروفایل مشتری یک کلید API ایجاد کنید.</p></div>
                        <div class="rounded-xl border border-white bg-white p-4"><span class="flex size-7 items-center justify-center rounded-lg bg-[#EAF2FF] text-xs font-black text-[#0069FF]">۲</span><h3 class="mt-4 text-sm font-black text-slate-900">هدر را اضافه کنید</h3><p class="mt-2 text-xs leading-6 text-slate-500">کلید را به شکل Bearer Token ارسال کنید.</p></div>
                        <div class="rounded-xl border border-white bg-white p-4"><span class="flex size-7 items-center justify-center rounded-lg bg-[#EAF2FF] text-xs font-black text-[#0069FF]">۳</span><h3 class="mt-4 text-sm font-black text-slate-900">اولین درخواست</h3><p class="mt-2 text-xs leading-6 text-slate-500">موجودی پروژه را با endpoint کیف پول بخوانید.</p></div>
                    </div>
                </section>

                <section id="authentication" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 md:p-8">
                    <p class="text-xs font-black tracking-[.14em] text-[#0069FF]">01 · AUTHENTICATION</p><h2 class="mt-2 text-2xl font-black text-slate-950">احراز هویت</h2><p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600">از پنل مشتری در بخش پروفایل، یک کلید بسازید. کلید را فقط هنگام ساخت می‌بینید و در همه درخواست‌ها به صورت Bearer Token ارسال می‌کنید.</p>
                    <div class="mt-6 rounded-xl bg-[#071B3A] p-4"><div class="flex items-center justify-between"><span class="text-xs font-bold text-slate-400">Authorization header</span><button type="button" @click="copy('Authorization: Bearer YOUR_API_KEY', 'auth')" class="rounded-md px-2 py-1 text-xs font-black text-blue-300 hover:bg-white/10" x-text="copied === 'auth' ? 'کپی شد' : 'کپی'">کپی</button></div><code class="mt-3 block overflow-x-auto font-mono text-sm leading-7 text-emerald-300" dir="ltr">Authorization: Bearer YOUR_API_KEY</code></div>
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs leading-6 text-slate-500"><span>Base URL: <code class="font-mono text-slate-700" dir="ltr">{{ $baseUrl }}</code></span><span>Content-Type: <code class="font-mono text-slate-700" dir="ltr">application/json</code></span></div>
                </section>

                <section id="wallet" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 p-6 md:p-8"><div class="flex flex-wrap items-center gap-3"><span class="rounded-md bg-emerald-100 px-2.5 py-1.5 font-mono text-xs font-black text-emerald-700" dir="ltr">GET</span><code class="font-mono text-sm font-bold text-slate-800" dir="ltr">/projects/{project_uuid}/wallet</code><span class="mr-auto rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500">200 OK</span></div><p class="mt-4 text-sm leading-7 text-slate-600">موجودی کیف پول فضای کاری را برمی‌گرداند. کلید باید به مشتری‌ای تعلق داشته باشد که در پروژه دسترسی مالی دارد.</p></div>
                    <div class="grid lg:grid-cols-2"><div class="border-b border-slate-200 p-6 lg:border-b-0 lg:border-l md:p-8"><div class="flex items-center justify-between"><span class="text-xs font-black text-slate-500">REQUEST EXAMPLE</span><div class="flex rounded-lg bg-slate-100 p-1"><button type="button" @click="tabs.wallet = 'curl'" :class="tabs.wallet === 'curl' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-500'" class="rounded-md px-2.5 py-1 text-[11px] font-black">cURL</button><button type="button" @click="tabs.wallet = 'php'" :class="tabs.wallet === 'php' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-500'" class="rounded-md px-2.5 py-1 text-[11px] font-black">PHP</button></div></div><div class="mt-3 rounded-xl bg-[#071B3A] p-4"><div class="flex justify-end"><button type="button" @click="copy(tabs.wallet === 'curl' ? @js($walletCurl) : @js('$ch = curl_init("'.$baseUrl.'/projects/YOUR_PROJECT_UUID/wallet");'), 'wallet-request')" class="text-xs font-black text-blue-300" x-text="copied === 'wallet-request' ? 'کپی شد' : 'کپی'">کپی</button></div><pre x-show="tabs.wallet === 'curl'" class="mt-2 overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-7 text-emerald-300" dir="ltr"><code>{{ $walletCurl }}</code></pre><pre x-cloak x-show="tabs.wallet === 'php'" class="mt-2 overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-7 text-emerald-300" dir="ltr"><code>$ch = curl_init('{{ $baseUrl }}/projects/YOUR_PROJECT_UUID/wallet');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer YOUR_API_KEY']);</code></pre></div></div><div class="bg-slate-50 p-6 md:p-8"><span class="text-xs font-black text-slate-500">RESPONSE · JSON</span><pre class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 font-mono text-xs leading-7 text-slate-700" dir="ltr"><code>{
  "data": {
    "project_id": "YOUR_PROJECT_UUID",
    "balance": 1250000,
    "currency": "IRR",
    "display_amount": "125,000 تومان"
  },
  "meta": { "request_id": "REQUEST_ID" }
}</code></pre></div></div>
                </section>

                <section id="transactions" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 p-6 md:p-8"><div class="flex flex-wrap items-center gap-3"><span class="rounded-md bg-emerald-100 px-2.5 py-1.5 font-mono text-xs font-black text-emerald-700" dir="ltr">GET</span><code class="font-mono text-sm font-bold text-slate-800" dir="ltr">/projects/{project_uuid}/wallet/transactions</code><span class="mr-auto rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500">PAGINATED</span></div><p class="mt-4 text-sm leading-7 text-slate-600">تراکنش‌های قابل مشاهده همان فضای کاری را به صورت صفحه‌بندی‌شده برمی‌گرداند.</p><div class="mt-5 grid gap-2 text-xs text-slate-600 sm:grid-cols-3"><div class="rounded-lg bg-slate-50 p-3"><code class="font-mono font-bold text-slate-800" dir="ltr">type</code><span class="mt-1 block leading-6">all, credit, debit, charge, refund</span></div><div class="rounded-lg bg-slate-50 p-3"><code class="font-mono font-bold text-slate-800" dir="ltr">per_page</code><span class="mt-1 block leading-6">۱ تا ۱۰۰ · پیش‌فرض ۲۵</span></div><div class="rounded-lg bg-slate-50 p-3"><code class="font-mono font-bold text-slate-800" dir="ltr">from / to</code><span class="mt-1 block leading-6">تاریخ مانند 2026-07-01</span></div></div></div>
                    <div class="grid lg:grid-cols-2"><div class="border-b border-slate-200 p-6 lg:border-b-0 lg:border-l md:p-8"><div class="flex items-center justify-between"><span class="text-xs font-black text-slate-500">REQUEST EXAMPLE</span><div class="flex rounded-lg bg-slate-100 p-1"><button type="button" @click="tabs.transactions = 'curl'" :class="tabs.transactions === 'curl' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-500'" class="rounded-md px-2.5 py-1 text-[11px] font-black">cURL</button><button type="button" @click="tabs.transactions = 'php'" :class="tabs.transactions === 'php' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-500'" class="rounded-md px-2.5 py-1 text-[11px] font-black">PHP</button></div></div><div class="mt-3 rounded-xl bg-[#071B3A] p-4"><div class="flex justify-end"><button type="button" @click="copy(tabs.transactions === 'curl' ? @js($transactionsCurl) : @js('$ch = curl_init("'.$baseUrl.'/projects/YOUR_PROJECT_UUID/wallet/transactions?per_page=25");'), 'transactions-request')" class="text-xs font-black text-blue-300" x-text="copied === 'transactions-request' ? 'کپی شد' : 'کپی'">کپی</button></div><pre x-show="tabs.transactions === 'curl'" class="mt-2 overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-7 text-emerald-300" dir="ltr"><code>{{ $transactionsCurl }}</code></pre><pre x-cloak x-show="tabs.transactions === 'php'" class="mt-2 overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-7 text-emerald-300" dir="ltr"><code>$ch = curl_init('{{ $baseUrl }}/projects/YOUR_PROJECT_UUID/wallet/transactions?per_page=25');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer YOUR_API_KEY']);</code></pre></div></div><div class="bg-slate-50 p-6 md:p-8"><span class="text-xs font-black text-slate-500">RESPONSE · JSON</span><pre class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 font-mono text-xs leading-7 text-slate-700" dir="ltr"><code>{
  "data": [{
    "id": 42,
    "type": "credit",
    "amount": 500000,
    "currency": "IRR",
    "created_at": "2026-07-13T08:30:00+03:30"
  }],
  "links": { "next": null },
  "meta": { "current_page": 1, "last_page": 1 }
}</code></pre></div></div>
                </section>

                <section id="errors" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">REFERENCE</p><h2 class="mt-2 text-2xl font-black text-slate-950">پاسخ خطا</h2><p class="mt-4 text-sm leading-8 text-slate-600">در خطاها کد HTTP و <code dir="ltr" class="font-mono text-slate-800">error.code</code> را بررسی کنید. برای پشتیبانی، Request ID را ارسال کنید.</p><div class="mt-5 grid gap-3 md:grid-cols-2"><pre class="overflow-x-auto rounded-xl bg-red-50 p-4 font-mono text-xs leading-7 text-red-900" dir="ltr"><code>401 Unauthorized
{ "error": { "code": "Unauthenticated" },
  "meta": { "request_id": "REQUEST_ID" } }</code></pre><pre class="overflow-x-auto rounded-xl bg-amber-50 p-4 font-mono text-xs leading-7 text-amber-900" dir="ltr"><code>403 Forbidden
{ "error": { "code": "project_forbidden" },
  "meta": { "request_id": "REQUEST_ID" } }</code></pre></div><div class="mt-3 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600"><strong class="text-slate-900">422</strong> پارامتر نامعتبر · <strong class="text-slate-900">429</strong> محدودیت درخواست · <strong class="text-slate-900">5xx</strong> خطای موقت سرویس.</div></section>

                <section id="security" class="scroll-mt-24 rounded-2xl border border-blue-100 bg-[#EEF5FF] p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">SECURITY</p><h2 class="mt-2 text-2xl font-black text-slate-950">نکات امنیتی</h2><div class="mt-5 grid gap-3 sm:grid-cols-2"><p class="rounded-xl border border-white bg-white/70 p-4 text-sm leading-7 text-blue-950">کلید را داخل Git، لاگ، مرورگر یا کد سمت کاربر قرار ندهید.</p><p class="rounded-xl border border-white bg-white/70 p-4 text-sm leading-7 text-blue-950">در صورت افشا، از پروفایل آن را لغو و کلید جدید بسازید.</p><p class="rounded-xl border border-white bg-white/70 p-4 text-sm leading-7 text-blue-950">برای هر سرویس یا محیط، کلید جداگانه بسازید.</p><p class="rounded-xl border border-white bg-white/70 p-4 text-sm leading-7 text-blue-950">درخواست‌ها با Request ID ثبت می‌شوند و اطلاعات محرمانه ذخیره نمی‌شود.</p></div></section>
            </main>
        </div>
    </div>
</div>
                <section id="transaction-detail" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 p-6 md:p-8"><div class="flex flex-wrap items-center gap-3"><span class="rounded-md bg-emerald-100 px-2.5 py-1.5 font-mono text-xs font-black text-emerald-700" dir="ltr">GET</span><code class="font-mono text-sm font-bold text-slate-800" dir="ltr">/projects/{project_uuid}/wallet/transactions/{transaction}</code><span class="mr-auto rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500">200 OK</span></div><h2 class="mt-4 text-xl font-black text-slate-950">Get one transaction</h2><p class="mt-2 text-sm leading-7 text-slate-600">یک تراکنش قابل مشاهده را با شناسه عددی آن برمی‌گرداند. همان Bearer Token، توانایی <code dir="ltr">wallet:read</code> و دسترسی مالی پروژه لازم است.</p></div>
                    <div class="grid lg:grid-cols-2"><div class="border-b border-slate-200 p-6 lg:border-b-0 lg:border-l md:p-8"><span class="text-xs font-black text-slate-500">cURL</span><div class="mt-3 rounded-xl bg-[#071B3A] p-4"><pre class="overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-7 text-emerald-300" dir="ltr"><code>{{ $transactionCurl }}</code></pre></div></div><div class="bg-slate-50 p-6 md:p-8"><span class="text-xs font-black text-slate-500">RESPONSE · JSON</span><pre class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 font-mono text-xs leading-7 text-slate-700" dir="ltr"><code>{
  "data": {
    "id": 42,
    "type": "credit",
    "amount": 500000,
    "display_amount": "50,000 تومان",
    "balance_before": 0,
    "balance_after": 500000,
    "description": "Wallet credit",
    "currency": "IRR",
    "created_at": "2026-07-13T08:30:00+03:30"
  },
  "meta": { "request_id": "REQUEST_ID" }
}</code></pre></div></div>
                    <div class="border-t border-slate-200 p-6 text-sm leading-8 text-slate-600">تراکنش باید متعلق به کیف پول مالک پروژه باشد و یا <code dir="ltr">metadata.project_id</code> آن با پروژه منطبق باشد یا این metadata را نداشته باشد. تراکنش پروژه دیگر یا مشتری دیگر با پاسخ <code dir="ltr">404 transaction_not_found</code> پنهان می‌شود.</div>
                </section>
@endsection
