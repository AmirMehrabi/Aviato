<dl class="grid gap-4 text-sm sm:grid-cols-2">
    @foreach(['location' => 'مرکز داده', 'rack_units' => 'فضای رزروشده (U)', 'power' => 'برق شامل سرویس', 'connectivity' => 'اتصال شبکه'] as $key => $label)
        <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 font-bold text-slate-900">{{ $spec[$key] }}</dd></div>
    @endforeach
</dl>
<div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-700">
    @if($spec['traffic_policy'] === 'included')
        <p><strong>{{ number_format($spec['included_traffic_gb']) }} GB</strong> ترافیک خروجی در هر ماه؛ مازاد هر GB: <strong>{{ $wallets->format($spec['traffic_price_per_gb'], $spec['currency']) }}</strong></p>
    @elseif($spec['traffic_policy'] === 'metered')
        <p>بدون سهمیه ترافیک؛ هر GB خروجی: <strong>{{ $wallets->format($spec['traffic_price_per_gb'], $spec['currency']) }}</strong></p>
    @elseif($spec['traffic_policy'] === 'unmetered')
        <p>بدون هزینه حجمی ترافیک. محدودیت سرعت و شرایط استفاده در قرارداد آمده است.</p>
    @else
        <p>اتصال و ترافیک طبق قرارداد ارتباطی جداگانه محاسبه می‌شود.</p>
    @endif
    @if(in_array($spec['traffic_policy'], ['included', 'metered']))
        <p class="mt-2 text-xs text-slate-500">ترافیک خروجی ماهانه، پس از تأیید مصرف صورتحساب می‌شود. ورودی رایگان است. هر GB برابر یک میلیارد بایت است. سهمیه از سالگرد ماهانه شروع سرویس تجدید می‌شود و انتقال ندارد؛ مازاد با نرخ بالا ادامه می‌یابد.</p>
    @endif
</div>
