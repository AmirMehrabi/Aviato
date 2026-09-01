@extends('customer.layout')

@section('title', 'داشبورد مشتری')
@section('header_title', 'میز کار')
@section('header_subtitle', 'ماشین‌ها و کارهای ضروری حساب شما، بدون اطلاعات اضافه')
@section('compact_header', true)
@section('primary_actions_in_content', true)

@php
    $activeNav = 'dashboard';
    $searchRows = [];

    if ($canManageVms) {
        $searchRows[] = [
            'title' => 'ساخت ماشین',
            'description' => 'انتخاب پلن ماشین مجازی و شروع مسیر ساخت',
            'type' => 'عملیات',
            'url' => route('customer.servers.create', [], false),
            'keywords' => 'ساخت ماشین vps server',
        ];
    }

    if ($canViewVms) {
        $searchRows[] = [
            'title' => 'ماشین‌های من',
            'description' => 'مشاهده همه ماشین‌های ابری',
            'type' => 'صفحه',
            'url' => route('customer.servers.index', [], false),
            'keywords' => 'servers ماشین سرورها',
        ];
    }

    foreach ($vmRows as $vm) {
        $searchRows[] = [
            'title' => $vm['name'],
            'description' => $vm['hostname'].' - '.$vm['ip'].' - '.$vm['status'],
            'type' => 'ماشین مجازی',
            'url' => $vm['url'],
            'keywords' => $vm['name'].' '.$vm['hostname'].' '.$vm['ip'].' '.$vm['status'],
        ];
    }
@endphp

@section('search_data')
@json($searchRows)
@endsection

@section('content')
    @if($newWorkspaceProject && (int) $newWorkspaceProject->id !== (int) $activeProject->id)
        <section class="mb-4 flex flex-col gap-4 rounded-2xl border border-[#B8D6FF] bg-[#EBF3FF] p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="new-workspace-title">
            <div class="min-w-0">
                <p class="text-xs font-black text-[#0069FF]">فضای کاری جدید</p>
                <h2 id="new-workspace-title" class="mt-1 text-lg font-black text-[#031B4E]">شما به «{{ $newWorkspaceProject->name }}» اضافه شده‌اید</h2>
                <p class="mt-1 text-sm font-bold leading-7 text-[#31527F]">مالک: {{ $newWorkspaceProject->owner?->name }}. ماشین‌ها، اعضا و پرداخت‌های این فضا از فضای فعلی شما جداست.</p>
            </div>
            <a href="{{ route('customer.projects.enter', $newWorkspaceProject, false) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">ورود به فضای کاری</a>
        </section>
    @endif

    @php
        $walletIsBlocked = $canViewBilling && ($wallet->is_locked || $wallet->balance < 0);
        $workspaceRoleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
        $workspaceRole = $workspaceRoleLabels[$activeMembership?->role ?? 'member'] ?? 'عضو';
        $attentionItems = collect([
            $canViewBilling && $wallet->is_locked
                ? ['tone' => 'red', 'title' => 'کیف پول قفل است', 'body' => 'برای ادامه استفاده، وضعیت کیف پول را بررسی کنید.', 'url' => route('customer.wallet.show', ['topup' => 1], false), 'action' => 'بررسی کیف پول']
                : null,
            $canViewVms && ($summary['failed'] ?? 0) > 0
                ? ['tone' => 'red', 'title' => 'آماده‌سازی ماشین ناموفق بوده است', 'body' => $summary['failed'].' ماشین به بررسی نیاز دارد.', 'url' => route('customer.servers.index', [], false), 'action' => 'بررسی ماشین']
                : null,
            $canViewVms && ($summary['deleting'] ?? 0) > 0
                ? ['tone' => 'amber', 'title' => 'حذف ماشین در حال انجام است', 'body' => $summary['deleting'].' ماشین هنوز در حال حذف است.', 'url' => route('customer.servers.index', [], false), 'action' => 'پیگیری وضعیت']
                : null,
        ])->filter()->values();
    @endphp

    <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-200/50 xl:flex-row xl:items-center xl:justify-between" aria-label="دسترسی‌های سریع">
        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <a
                href="{{ route('customer.projects.show', $activeProject, false) }}"
                class="group flex min-h-11 min-w-0 items-center gap-2.5 rounded-xl bg-slate-50 px-3 text-slate-700 transition-colors hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
            >
                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-[#0069FF] text-xs font-black text-white" aria-hidden="true">
                    {{ mb_substr($activeProject->name ?: 'ف', 0, 1) }}
                </span>
                <span class="min-w-0">
                    <span class="block text-[10px] font-black text-slate-400">فضای کاری فعال</span>
                    <span class="block truncate text-xs font-black">{{ $activeProject->name }} · {{ $workspaceRole }}</span>
                </span>
            </a>

            @if ($canViewBilling)
                <div class="flex min-h-11 min-w-0 items-center gap-2.5 rounded-xl px-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $walletIsBlocked ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}" aria-hidden="true">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[10px] font-black text-slate-400">موجودی کیف پول</span>
                        <span class="block truncate text-sm font-black {{ $walletIsBlocked ? 'text-red-700' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</span>
                    </span>
                    <a
                        href="{{ route('customer.wallet.show', ['topup' => 1], false) }}"
                        class="mr-1 rounded-lg px-2 py-1.5 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                    >
                        شارژ کیف پول
                    </a>
                </div>
            @endif

            @if ($canViewVms)
                <div class="flex min-h-11 items-center gap-2.5 rounded-xl px-3 text-xs font-black text-slate-600">
                    <span class="relative flex size-3 items-center justify-center" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    </span>
                    <span>{{ $summary['running'] }} روشن از {{ $dashboardStats['total'] }} ماشین</span>
                </div>
            @endif
        </div>

        @if ($canManageVms)
            <a
                href="{{ route('customer.servers.create', [], false) }}"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition-[background-color,transform,box-shadow] hover:bg-[#0050D0] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
            >
                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                </svg>
                ساخت ماشین جدید
            </a>
        @endif
    </section>

    @if ($attentionItems->isNotEmpty())
        <section class="mt-3 space-y-2" aria-label="موارد نیازمند توجه">
            @foreach ($attentionItems as $item)
                <div class="flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between {{ $item['tone'] === 'red' ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-950' }}">
                    <div class="flex min-w-0 items-start gap-3">
                        <svg class="mt-0.5 size-5 shrink-0 {{ $item['tone'] === 'red' ? 'text-red-600' : 'text-amber-600' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M12 8v5M12 17h.01" stroke-linecap="round"/>
                            <path d="M10.3 3.9h3.4L22 17.8A2 2 0 0 1 20.3 21H3.7A2 2 0 0 1 2 17.8L10.3 3.9Z" stroke-linejoin="round"/>
                        </svg>
                        <p class="text-xs font-bold leading-6">
                            <span class="font-black">{{ $item['title'] }}</span>
                            <span class="mr-1">{{ $item['body'] }}</span>
                        </p>
                    </div>
                    <a href="{{ $item['url'] }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-white px-3 text-xs font-black shadow-sm transition-colors hover:bg-white/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                        {{ $item['action'] }}
                    </a>
                </div>
            @endforeach
        </section>
    @endif

    @if (! $canViewVms)
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex rounded-lg bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">دسترسی مالی</span>
                    <h2 class="mt-3 text-xl font-black text-slate-950">نمای مالی فضای کاری</h2>
                    <p class="mt-2 text-sm font-bold leading-7 text-slate-500">دسترسی شما به کیف پول و صورتحساب‌های این فضای کاری محدود است. اطلاعات ماشین‌ها برای این نقش نمایش داده نمی‌شود.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white transition-colors hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده کیف پول</a>
                    <a href="{{ route('customer.invoices.index', [], false) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition-colors hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده صورتحساب‌ها</a>
                </div>
            </div>
        </section>
    @elseif ($vmRows->isEmpty())
        <section class="mt-4 rounded-2xl border border-[#B8D6FF] bg-[#F8FBFF] px-5 py-10 text-center shadow-sm shadow-[#0069FF]/10">
            <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M4 5h16v6H4V5Zm0 8h16v6H4v-6Zm4-5h.01M8 16h.01M12 8h4M12 16h4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-black text-slate-950">هنوز ماشینی ندارید</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm font-bold leading-7 text-slate-500">یک ماشین بسازید و پس از آماده‌شدن، وضعیت و کنسول آن را همین‌جا ببینید.</p>
            @if ($canManageVms)
                <a href="{{ route('customer.servers.create', [], false) }}" class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-6 text-sm font-black text-white transition-[background-color,transform] hover:bg-[#0050D0] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                    </svg>
                    ساخت اولین ماشین
                </a>
            @endif
        </section>
    @else
        <section class="mt-4" aria-labelledby="my-machines-heading">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="my-machines-heading" class="text-lg font-black text-slate-950">ماشین‌های من</h2>
                    <p class="mt-1 text-xs font-bold text-slate-500">وضعیت و راه‌های دسترسی به ماشین‌های فضای کاری فعال</p>
                </div>
                <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-3 text-xs font-black text-slate-600 transition-colors hover:bg-white hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    مشاهده همه
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>

            <div class="space-y-2.5">
                @foreach ($vmRows->take(5) as $vm)
                    <article class="rounded-2xl border bg-white p-4 shadow-sm shadow-slate-200/40 transition-[border-color,box-shadow] hover:border-[#B8D6FF] hover:shadow-md hover:shadow-[#0069FF]/[0.08] {{ $vm['needsAttention'] ? 'border-red-200' : 'border-slate-200' }}">
                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(15rem,.75fr)_auto] xl:items-center">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $vm['dot'] }}" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-sm font-black text-slate-950" dir="ltr">{{ $vm['name'] }}</h3>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-black {{ $vm['statusClass'] }}">
                                            <span class="size-1.5 rounded-full {{ $vm['dot'] }}" aria-hidden="true"></span>
                                            {{ $vm['status'] }}
                                        </span>
                                        @if ($vm['provisioningStatus'] !== 'آماده')
                                            <span class="inline-flex rounded-lg px-2.5 py-1 text-[11px] font-black {{ $vm['provisioningClass'] }}">{{ $vm['provisioningStatus'] }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 break-all text-xs font-bold text-slate-400" dir="ltr">{{ $vm['hostname'] }} · {{ $vm['ip'] }}</p>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-2">
                                <div class="flex min-h-10 items-center gap-2 rounded-xl bg-slate-50 px-3">
                                    <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="7" y="7" width="10" height="10" rx="2"/>
                                        <path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3" stroke-linecap="round"/>
                                    </svg>
                                    <div>
                                        <dt class="text-[10px] font-black text-slate-400">CPU</dt>
                                        <dd class="text-xs font-black text-slate-700">{{ $vm['cpu'] }}</dd>
                                    </div>
                                </div>
                                <div class="flex min-h-10 items-center gap-2 rounded-xl bg-slate-50 px-3">
                                    <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="5" y="6" width="14" height="12" rx="2"/>
                                        <path d="M8 10h8M8 14h8M9 3v3M15 3v3M9 18v3M15 18v3" stroke-linecap="round"/>
                                    </svg>
                                    <div>
                                        <dt class="text-[10px] font-black text-slate-400">RAM</dt>
                                        <dd class="text-xs font-black text-slate-700">{{ $vm['ram'] }}</dd>
                                    </div>
                                </div>
                            </dl>

                            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                                @if ($vm['consoleReady'])
                                    <a
                                        href="{{ $vm['consoleUrl'] }}"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition-[background-color,transform] hover:bg-[#0069FF] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                                    >
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <path d="m5 7 5 5-5 5M12 17h7" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        باز کردن کنسول
                                    </a>
                                    <a
                                        href="{{ $vm['url'] }}"
                                        class="inline-flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                                        aria-label="مدیریت {{ $vm['name'] }}"
                                        title="مدیریت ماشین"
                                    >
                                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 8.95 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.53-1H3v-4h.08A1.7 1.7 0 0 0 4.6 8.95a1.7 1.7 0 0 0-.34-1.88L4.2 7l2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15.05 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06L19.82 7l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.61.6 1.1 1.2 1H21v4h-.4a1.7 1.7 0 0 0-1.2 1Z" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                @else
                                    <a
                                        href="{{ $vm['url'] }}"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border px-4 text-xs font-black transition-[border-color,background-color,color,transform] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 {{ $vm['needsAttention'] ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 focus-visible:outline-red-600' : 'border-slate-200 bg-white text-slate-700 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-[#0069FF]' }}"
                                    >
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 8.95 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.53-1H3v-4h.08A1.7 1.7 0 0 0 4.6 8.95a1.7 1.7 0 0 0-.34-1.88L4.2 7l2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15.05 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06L19.82 7l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.61.6 1.1 1.2 1H21v4h-.4a1.7 1.7 0 0 0-1.2 1Z" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        {{ $vm['needsAttention'] ? 'بررسی مشکل' : 'مدیریت ماشین' }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($canViewBilling)
        <section class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="billing-summary-heading">
            <h2 id="billing-summary-heading" class="sr-only">خلاصه مالی</h2>
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto] lg:items-center">
                <div>
                    <p class="text-[10px] font-black text-slate-400">برآورد هزینه ماه جاری</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ $wallets->format($dashboardStats['monthly_spend']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400">مصرف ثبت‌نشده</p>
                    <p class="mt-1 text-sm font-black {{ $pendingUsage > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $wallets->format($pendingUsage) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400">آخرین صورتحساب</p>
                    @if ($latestInvoice)
                        <a href="{{ route('customer.invoices.show', $latestInvoice, false) }}" class="mt-1 inline-flex text-sm font-black text-[#0069FF] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $latestInvoice->number }}</a>
                    @else
                        <p class="mt-1 text-sm font-black text-slate-600">هنوز صادر نشده</p>
                    @endif
                </div>
                <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-xs font-black text-slate-600 transition-colors hover:bg-white hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    جزئیات مصرف
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </section>
    @endif
@endsection
