<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پنل مشتریان</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3efe6] p-6 text-slate-950">
    <main class="mx-auto max-w-5xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] bg-[#0A3D37] p-8 text-white shadow-2xl shadow-[#0A3D37]/10">
            <p class="text-sm font-bold text-emerald-50/60">Customer Portal</p>
            <h1 class="mt-2 text-3xl font-black">سلام {{ $customer->name }}</h1>
            <p class="mt-3 text-emerald-50/75">کیف پول شما برای پرداخت مصرف Pay As You Go استفاده می‌شود.</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(360px,1.2fr)]">
            <div class="rounded-[2rem] bg-white p-6 shadow-xl shadow-[#0A3D37]/10">
                <p class="text-sm font-bold text-slate-500">موجودی کیف پول</p>
                <p class="mt-4 text-4xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-[#105D52]' }}">{{ $wallets->format($wallet->balance) }}</p>
                <p class="mt-3 text-sm text-slate-500">وضعیت: {{ $wallet->is_locked ? 'قفل شده' : 'فعال' }}</p>
            </div>

            <div class="rounded-[2rem] bg-white p-6 shadow-xl shadow-[#0A3D37]/10">
                <h2 class="text-xl font-black">آخرین تراکنش‌ها</h2>
                <div class="mt-5 space-y-3">
                    @forelse($transactions as $transaction)
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4">
                            <div>
                                <p class="font-black">{{ $transaction->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->created_at?->format('Y/m/d H:i') }}</p>
                            </div>
                            <span class="font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $wallets->format($transaction->amount) }}</span>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">هنوز تراکنشی ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('customer.logout', [], false) }}">@csrf <button class="rounded-2xl bg-[#0A3D37] px-5 py-3 font-black text-white">خروج</button></form>
    </main>
</body>
</html>
