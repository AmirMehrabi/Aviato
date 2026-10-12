@php
    $retentionNotice = isset($activeProject) && ($walletIsDepleted ?? false)
        ? app(\App\Services\VmWalletRetentionService::class)->customerNotice($activeProject)
        : null;
    $canFundRetention = isset($activeProject) && app(\App\Services\ProjectAccessService::class)->canViewBilling($activeProject, $customer);
@endphp
@if ($retentionNotice)
    <section role="alert" aria-labelledby="wallet-retention-title" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-950 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-black text-rose-700">حفظ اطلاعات سرورها</p>
                <h2 id="wallet-retention-title" class="mt-2 text-lg font-black">{{ $retentionNotice['expired'] ? 'مهلت نگهداری تمام شده است؛ حذف سرورها در حال انجام است' : 'برای جلوگیری از حذف دائمی، کیف پول را شارژ کنید' }}</h2>
                <p class="mt-3 text-sm leading-7">موجودی قابل استفاده کیف پول مالک تمام شده است. مهلت نگهداری {{ \App\Support\PersianDigits::convert((string) $retentionNotice['count']) }} سرور این فضای کاری تا <strong class="whitespace-nowrap">{{ \App\Support\PersianDigits::convert(\App\Support\Jalali::format($retentionNotice['deadline'])) }}</strong> به وقت تهران است.</p>
                <p class="mt-2 text-sm leading-7">پس از مهلت، سرور، دیسک‌ها و بکاپ‌های آن دائماً حذف می‌شوند. هزینه نگهداری تا پایان مهلت ادامه دارد. شارژ باید بدهی و هزینه مصرف اخیر را پوشش دهد تا موجودی قابل استفاده مثبت شود؛ شارژ ناقص مهلت را تمدید نمی‌کند.</p>
                @if ($retentionNotice['expired'])
                    <p class="mt-2 text-sm font-bold leading-7">اگر حذف هنوز شروع نشده باشد، شارژ می‌تواند آن را لغو کند. اطلاعات سرور حذف‌شده قابل بازیابی نیست.</p>
                @else
                    <p class="mt-2 text-sm leading-7">پس از شارژ، اتصال شبکه برمی‌گردد و سرورهایی که به‌دلیل موجودی خاموش شده‌اند خودکار روشن می‌شوند. سرورهایی که خودتان خاموش کرده‌اید، خاموش می‌مانند.</p>
                @endif
                @if ($retentionNotice['requires_multiple_payments'])
                    <p class="mt-2 text-sm font-bold">مبلغ مورد نیاز از سقف هر پرداخت بیشتر است؛ برای مثبت شدن موجودی، چند پرداخت انجام دهید.</p>
                @endif
            </div>
            @if ($canFundRetention)
                <a href="{{ route('customer.wallet.show', ['topup' => 1, 'restore' => 1], false) }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-rose-700 px-5 py-3 text-sm font-black text-white transition hover:bg-rose-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">شارژ کیف پول و حفظ سرورها</a>
            @else
                <p class="rounded-xl bg-white px-4 py-3 text-sm font-bold leading-7">برای شارژ، به مالک یا عضو دارای دسترسی مالی این فضای کاری اطلاع دهید.</p>
            @endif
        </div>
    </section>
@endif
