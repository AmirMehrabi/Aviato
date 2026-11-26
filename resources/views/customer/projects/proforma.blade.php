<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پیش‌فاکتور - {{ $project->name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1rem; background: #f8fafc; color: #0f172a; font: 1rem/1.65 Vazirmatn, Tahoma, sans-serif; }
        main { max-width: 58rem; margin-inline: auto; }
        a, button { color: #004fb8; font: inherit; }
        a:focus-visible, button:focus-visible { outline: 2px solid #0069ff; outline-offset: 3px; }
        .actions { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-block-end: 1rem; }
        .actions button { padding: .45rem 1rem; background: #0057cb; color: white; border: 0; border-radius: .5rem; cursor: pointer; }
        .sheet { padding: clamp(1rem, 4vw, 2.5rem); background: white; border: 1px solid #cbd5e1; border-radius: .75rem; }
        .sheet-header { padding-block-end: 1.25rem; border-block-end: 2px solid #031b4e; }
        h1, p, dd { margin: 0; }
        h1 { color: #031b4e; font-size: 1.65rem; }
        .workspace { font-size: 1.2rem; font-weight: 700; overflow-wrap: anywhere; }
        .metadata { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); gap: .75rem 1.25rem; margin-block: 1.25rem; }
        .metadata div { min-width: 0; }
        dt { color: #475569; font-size: .875rem; }
        dd { font-weight: 600; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .9rem; }
        th, td { padding: .6rem .5rem; text-align: start; vertical-align: top; overflow-wrap: anywhere; border-block-end: 1px solid #cbd5e1; }
        thead th { color: #031b4e; border-block-end: 2px solid #031b4e; }
        tbody th { font-weight: 600; }
        .specs { width: 30%; }
        .specs bdi { display: inline-block; margin-inline-end: .3rem; }
        .status { width: 15%; }
        .price { width: 23%; text-align: end; }
        .summary { width: min(100%, 27rem); margin-block-start: 1.25rem; margin-inline-start: auto; }
        .totals { margin: 0; }
        .totals div { display: flex; justify-content: space-between; gap: .75rem; padding-block: .35rem; }
        .totals dd { text-align: end; }
        .totals .total { border-block-start: 2px solid #031b4e; }
        .total dt { font-size: 1rem; font-weight: 700; color: #031b4e; }
        .notice { margin-block-start: .75rem; padding: .65rem .8rem; border: 1px solid #cbd5e1; border-inline-start: 3px solid #0057cb; font-weight: 700; }
        .unavailable { color: #854d0e; }
        .notes { margin-block-start: 1.25rem; font-size: .85rem; color: #475569; }
        @media screen and (max-width: 40rem) {
            table { font-size: .8rem; }
            th, td { padding: .45rem .3rem; }
            .specs { width: 26%; }
            .status { width: 17%; }
            .price { width: 25%; }
        }
        @page { size: A4; margin: 12mm; }
        @media print {
            body { padding: 0; background: white; }
            main { max-width: none; }
            .actions { display: none; }
            .sheet { padding: 0; border: 0; }
            thead { display: table-header-group; }
            tr, .sheet-header, .metadata, .summary, .notes { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main>
        <div class="actions">
            <a href="{{ route('customer.projects.show', ['project' => $project, 'section' => 'billing'], false) }}">بازگشت به مالی و هشدارها</a>
            <button type="button" onclick="window.print()">چاپ / ذخیره PDF</button>
        </div>

        <article class="sheet" aria-labelledby="proforma-title">
            <header class="sheet-header">
                <h1 id="proforma-title">پیش‌فاکتور فضای کاری</h1>
                <p class="workspace" dir="auto">{{ $project->name }}</p>
            </header>

            <dl class="metadata">
                <div><dt>مسئول پرداخت</dt><dd dir="auto">{{ filled($project->owner?->name) ? $project->owner->name : '—' }}</dd></div>
                <div><dt>ماه مرجع برآورد</dt><dd>{{ \App\Support\PersianDigits::convert($referenceMonth) }}</dd></div>
                <div><dt>بازه میلادی</dt><dd><bdi>{{ \App\Support\PersianDigits::convert($periodStart) }}</bdi> تا <bdi>{{ \App\Support\PersianDigits::convert($periodEnd) }}</bdi></dd></div>
                <div><dt>زمان تهیه (شمسی)</dt><dd><bdi>{{ \App\Support\PersianDigits::convert($generatedAt) }}</bdi></dd></div>
                <div><dt>تعداد ماشین‌های مجازی</dt><dd>{{ \App\Support\PersianDigits::format($rows->count()) }}</dd></div>
                <div><dt>مبنای برآورد</dt><dd>{{ \App\Support\PersianDigits::format($hoursPerMonth) }} ساعت با نرخ روشن</dd></div>
            </dl>

            @if($rows->isEmpty())
                <p>این فضای کاری ماشین مجازی برای درج در پیش‌فاکتور ندارد.</p>
            @else
                <table aria-label="برآورد هزینه ماشین‌های مجازی">
                    <thead>
                        <tr>
                            <th scope="col">ماشین مجازی</th>
                            <th scope="col" class="specs">مشخصات</th>
                            <th scope="col" class="status">وضعیت</th>
                            <th scope="col" class="price">برآورد پیش از مالیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as ['vm' => $vm, 'price' => $price])
                            <tr>
                                <th scope="row"><bdi>{{ filled($vm->display_name) ? $vm->display_name : '—' }}</bdi></th>
                                <td class="specs">
                                    <bdi>{{ \App\Support\PersianDigits::convert((string) $vm->cpu_cores) }} vCPU</bdi>
                                    <bdi>{{ \App\Support\PersianDigits::convert((string) $vm->ram_gb) }} GB RAM</bdi>
                                    <bdi>{{ \App\Support\PersianDigits::convert((string) $vm->disk_gb) }} GB Disk</bdi>
                                    <bdi>{{ \App\Support\PersianDigits::convert((string) $vm->ip_count) }} IP</bdi>
                                </td>
                                <td>{{ $vm->customerStatusLabel() ?: '—' }}</td>
                                <td class="price">
                                    @if($price === null)
                                        <span class="unavailable">قیمت در دسترس نیست</span>
                                    @else
                                        {{ $wallets->format($price['subtotal'], $currency) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <section class="summary" aria-label="خلاصه برآورد">
                @if($totals === null)
                    <p class="unavailable">قیمت یک یا چند ماشین مجازی در دسترس نیست. پس از تکمیل قیمت‌ها، جمع پیش‌فاکتور قابل محاسبه است.</p>
                @else
                    <dl class="totals">
                        <div><dt>جمع پیش از مالیات</dt><dd>{{ $wallets->format($totals['subtotal'], $currency) }}</dd></div>
                        @if($taxRate > 0)
                            <div><dt>مالیات ارزش افزوده ({{ \App\Support\PersianDigits::convert((string) $taxRate) }}٪)</dt><dd>{{ $wallets->format($totals['tax'], $currency) }}</dd></div>
                        @endif
                        <div class="total"><dt>جمع برآورد</dt><dd>{{ $wallets->format($totals['total'], $currency) }}</dd></div>
                    </dl>
                @endif
                <p class="notice">هزینه مصرف ترافیک شبکه در این پیش‌فاکتور لحاظ نشده است.</p>
            </section>

            <footer class="notes">
                <p>این پیش‌فاکتور برآورد هزینه است. هزینه نهایی ماشین‌ها بر اساس مصرف ساعتی محاسبه می‌شود.</p>
                <p>خدماتی مانند پشتیبان‌گیری و دیسک اضافه به‌صورت جداگانه محاسبه می‌شوند.</p>
            </footer>
        </article>
    </main>
</body>
</html>
