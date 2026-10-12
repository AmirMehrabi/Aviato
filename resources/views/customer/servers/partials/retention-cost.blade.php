@if ($server->status === \App\Models\VirtualMachine::STATUS_STOPPED)
    <section class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-label="هزینه نگهداری سرور خاموش">
        <h2 class="font-black text-amber-950">{{ $server->customerStatusLabel() }}</h2>
        <p class="mt-2 text-sm leading-7 text-amber-900">در حالت خاموش، داده‌ها و IP این سرور نگهداری می‌شوند. نگهداری این سرور <strong>{{ $wallets->format((int) ceil($billing->persistentHourly($server) * 24)) }} در روز</strong> است. دیسک اضافه و بکاپ هزینه جداگانه دارند.</p>
        @if ($server->wallet_retention_expires_at)
            <p class="mt-2 text-sm leading-7 text-amber-900">مهلت حفظ این سرور: <strong>{{ \App\Support\PersianDigits::convert(\App\Support\Jalali::format($server->wallet_retention_expires_at)) }}</strong> به وقت تهران.</p>
        @endif
        @if ($canManageServer)
            <a href="{{ route('customer.servers.tab', [$server, 'tab' => 'delete'], false) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-950">به این سرور نیاز ندارم؛ حذف دائمی</a>
        @endif
    </section>
@endif
