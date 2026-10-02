@extends('layouts.admin')

@section('title', 'داشبورد عملیات آویاتو')

@inject('wallets', 'App\Services\WalletService')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-6 md:px-8 lg:px-10">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">داشبورد مدیریت</h1>
                <p class="mt-1 text-sm leading-6 text-slate-600">وضعیت پرداخت، مصرف و مواردی که اکنون به اقدام نیاز دارند.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-600">به‌روزرسانی: <time datetime="{{ $refreshedAt->toIso8601String() }}">{{ $refreshedAt->format('H:i') }}</time></span>

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', array_filter(['period' => $period, 'category' => $dashboard['category']])) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition-colors hover:border-[#0069FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">به‌روزرسانی</a>
@endadminRoute

            </div>
        </header>

        @if($finance !== null)
            @include('admin._dashboard-finance')
        @endif

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="action-queue-heading">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="action-queue-heading" class="text-lg font-bold text-slate-950">صف اقدام‌ها</h2>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ number_format($dashboard['total_count']) }} مورد فعال</span>
                        @if ($dashboard['filter_label'])
                            <span class="rounded-lg bg-[#EBF3FF] px-2.5 py-1 text-xs font-semibold text-[#0059DB]">{{ $dashboard['filter_label'] }}</span>

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', ['period' => $period]) }}" class="text-sm font-semibold text-[#0059DB] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">نمایش همه</a>
@endadminRoute

                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">ابتدا موارد بحرانی و قدیمی‌تر نمایش داده می‌شوند.</p>
                </div>
                @if ($dashboard['hidden_count'] > 0)

@adminRoute('admin.dashboard.warnings.restore')
<form method="POST" action="{{ route('admin.dashboard.warnings.restore') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-[#0059DB] hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $dashboard['category'] ? 'بازیابی همه موارد پنهان‌شده' : 'نمایش '.number_format($dashboard['hidden_count']).' مورد پنهان‌شده' }}</button>
                    </form>
@endadminRoute

                @endif
            </div>

            @if ($dashboard['items']->isNotEmpty())
                <div class="divide-y divide-slate-100">
                    @foreach ($dashboard['items'] as $item)
                        <article class="grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $item['severity'] === 'critical' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800' }}">{{ $item['category'] }}</span>
                                    <span class="text-xs text-slate-600" title="{{ $item['occurred_at']->format('Y-m-d H:i') }}">{{ $item['age'] }}</span>
                                </div>
                                <h3 class="mt-2 break-words text-base font-semibold text-slate-950" dir="auto">{{ $item['title'] }}</h3>
                                <p class="mt-1 break-words text-sm text-slate-600" dir="auto">{{ $item['meta'] }}</p>
                            </div>
                            <div class="flex items-center gap-2 md:justify-end">
                                <a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition-colors hover:border-[#0069FF] hover:bg-[#EBF3FF] hover:text-[#0059DB] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $item['action'] }}</a>

@adminRoute('admin.dashboard.warnings.dismiss')
<form method="POST" action="{{ route('admin.dashboard.warnings.dismiss') }}">
                                    @csrf
                                    <input type="hidden" name="warning_key" value="{{ $item['key'] }}">
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl px-3 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-600" aria-label="پنهان کردن {{ $item['title'] }}">پنهان کردن</button>
                                </form>
@endadminRoute

                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-semibold text-slate-900">{{ $dashboard['total_count'] > 0 && $dashboard['hidden_count'] > 0 ? 'مورد قابل مشاهده‌ای باقی نمانده است' : ($dashboard['category'] ? 'در این دسته مورد نیازمند اقدامی وجود ندارد' : 'مورد نیازمند اقدامی وجود ندارد') }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $dashboard['total_count'] > 0 && $dashboard['hidden_count'] > 0 ? 'موارد فعال پنهان‌شده را از بالای این بخش بازیابی کنید.' : ($dashboard['category'] ? 'برای دیدن سایر اقدام‌ها، فیلتر را بردارید.' : 'وضعیت بخش‌های اصلی را در خلاصه پایین بررسی کنید.') }}</p>
                </div>
            @endif

            @if ($dashboard['page'] > 1 || $dashboard['remaining_count'] > 0)
                <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3 text-sm" aria-label="صفحات صف اقدام‌ها">
                    <span class="text-slate-600">{{ number_format($dashboard['remaining_count']) }} مورد دیگر</span>
                    <div class="flex gap-2">
                        @if ($dashboard['page'] > 1)

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', array_filter(['page' => $dashboard['page'] - 1, 'category' => $dashboard['category'], 'period' => $period])) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">قبلی</a>
@endadminRoute

                        @endif
                        @if ($dashboard['remaining_count'] > 0)

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', array_filter(['page' => $dashboard['page'] + 1, 'category' => $dashboard['category'], 'period' => $period])) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">بعدی</a>
@endadminRoute

                        @endif
                    </div>
                </nav>
            @endif
        </section>

        <section class="mt-6" aria-labelledby="health-heading">
            <h2 id="health-heading" class="text-base font-bold text-slate-950">خلاصه وضعیت</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($dashboard['health'] as $item)

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', ['category' => $item['category'], 'period' => $period]) }}" @if ($dashboard['category'] === $item['category']) aria-current="page" @endif class="flex min-h-24 items-center justify-between gap-3 rounded-2xl border {{ $dashboard['category'] === $item['category'] ? 'border-[#0069FF] bg-[#F8FBFF]' : 'border-slate-200 bg-white' }} p-4 transition-colors hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                        <span class="text-sm font-semibold text-slate-700">{{ $item['label'] }}</span>
                        <span class="text-2xl font-bold {{ $item['count'] > 0 ? 'text-slate-950' : 'text-slate-500' }}">{{ number_format($item['count']) }}</span>
                    </a>
@endadminRoute

                @endforeach
            </div>
        </section>
    </div>
@endsection
