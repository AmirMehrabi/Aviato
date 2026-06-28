<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش فاکتور - {{ $project->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, 'B Nazanin', Arial, sans-serif; background: #f5f5f5; padding: 20px; color: #1a1a2e; }
        .invoice { max-width: 900px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid #ddd; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid #031B4E; padding-bottom: 20px; }
        .header-right h1 { font-size: 24px; color: #031B4E; }
        .header-right p { font-size: 13px; color: #666; margin-top: 5px; }
        .header-left { text-align: left; }
        .header-left .title { font-size: 20px; font-weight: bold; color: #031B4E; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .meta-item { background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .meta-item .label { font-size: 11px; color: #64748b; font-weight: bold; }
        .meta-item .value { font-size: 14px; font-weight: bold; color: #0f172a; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        thead { background: #031B4E; color: #fff; }
        th { padding: 12px 14px; font-size: 12px; font-weight: bold; text-align: right; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid #eee; }
        tbody tr:hover { background: #f8fafc; }
        .tax-row { background: #fffbeb; }
        .tax-row td { color: #92400e; font-weight: bold; }
        .summary { margin-top: 20px; display: flex; justify-content: flex-end; }
        .summary-box { background: #f1f5f9; border: 2px solid #031B4E; border-radius: 12px; padding: 20px 30px; min-width: 300px; }
        .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .summary-row.total { border-top: 2px solid #031B4E; margin-top: 8px; padding-top: 10px; font-size: 18px; font-weight: bold; color: #031B4E; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #999; text-align: center; }
        .print-btn { position: fixed; bottom: 30px; left: 30px; background: #0069FF; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 12px rgba(0,105,255,0.3); }
        .print-btn:hover { background: #0050D0; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { border: none; padding: 20px; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <div class="header-right">
                <h1>{{ $project->name }}</h1>
                <p>مسئول پرداخت: {{ $project->owner?->name }}</p>
                <p>{{ $project->owner?->email ?: $project->owner?->phone }}</p>
            </div>
            <div class="header-left">
                <div class="title">پیش فاکتور</div>
                <p style="font-size: 13px; color: #666; margin-top: 5px;">Proforma Invoice</p>
            </div>
        </div>

        <div class="meta">
            <div class="meta-item">
                <div class="label">دوره صورتحساب</div>
                <div class="value">{{ \App\Support\Jalali::formatMonthYear($jalaliYear, $jalaliMonth) }}</div>
            </div>
            <div class="meta-item">
                <div class="label">بازه میلادی</div>
                <div class="value">{{ $periodStart->format('Y/m/d') }} تا {{ $periodEnd->format('Y/m/d') }}</div>
            </div>
            <div class="meta-item">
                <div class="label">تعداد ماشین‌ها</div>
                <div class="value">{{ number_format($project->virtual_machines_count) }} ماشین</div>
            </div>
            <div class="meta-item">
                <div class="label">تاریخ صدور</div>
                <div class="value">{{ now()->format('Y/m/d H:i') }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ماشین مجازی</th>
                    <th>مشخصات</th>
                    <th>سرور</th>
                    <th>وضعیت</th>
                    <th style="text-align: left">هزینه ماهانه (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @php($taxRate = \App\Models\AppSetting::taxRatePercentage())
                @php($taxEnabled = \App\Models\AppSetting::taxEnabled())
                @php($subtotal = 0)
                @foreach($project->virtualMachines as $index => $vm)
                    @php($price = $vmPrices->get($vm->uuid, 0))
                    @php($priceToman = $price / 10)
                    @php($subtotal += $priceToman)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td style="font-weight: bold">{{ $vm->display_name }}</td>
                        <td>{{ $vm->cpu_cores }} vCPU / {{ $vm->ram_gb }}GB RAM / {{ $vm->disk_gb }}GB Disk / {{ $vm->ip_count }} IP</td>
                        <td>{{ $vm->proxmoxServer?->name ?: '—' }}</td>
                        <td>
                            @if($vm->status === 'running')
                                <span style="color: #16a34a; font-weight: bold">فعال</span>
                            @elseif($vm->status === 'stopped')
                                <span style="color: #d97706; font-weight: bold">متوقف</span>
                            @else
                                <span style="color: #6b7280">{{ $vm->status }}</span>
                            @endif
                        </td>
                        <td style="text-align: left; font-weight: bold">{{ number_format($priceToman) }}</td>
                    </tr>
                @endforeach
                @if($taxEnabled && $taxRate > 0)
                    @php($taxAmount = (int) round($subtotal * $taxRate / 100))
                    <tr class="tax-row">
                        <td colspan="5" style="text-align: left; font-weight: bold">مالیات ارزش افزوده ({{ number_format($taxRate, 0) }}٪)</td>
                        <td style="text-align: left; font-weight: bold">{{ number_format($taxAmount) }}</td>
                    </tr>
                @else
                    @php($taxAmount = 0)
                @endif
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-box">
                <div class="summary-row">
                    <span>جمع موارد:</span>
                    <span>{{ number_format($subtotal) }} تومان</span>
                </div>
                @if($taxEnabled && $taxRate > 0)
                <div class="summary-row">
                    <span>مالیات ({{ number_format($taxRate, 0) }}٪):</span>
                    <span>{{ number_format($taxAmount) }} تومان</span>
                </div>
                @endif
                <div class="summary-row total">
                    <span>جمع کل:</span>
                    <span>{{ number_format($subtotal + $taxAmount) }} تومان</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>این پیش فاکتور صرفاً جهت اطلاع‌رسانی بوده و به معنای صدور صورتحساب رسمی نیست.</p>
            <p style="margin-top: 5px">هزینه‌های واقعی بر اساس مصرف ساعتی ماشین‌های مجازی محاسبه می‌شوند.</p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">چاپ / PDF</button>
</body>
</html>
