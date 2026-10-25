<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use App\Models\DomainProduct;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DomainPricingService
{
    public function __construct(private readonly DomainProvider $provider) {}

    public function sync(): int
    {
        $prices = $this->provider->request('GET', '/final/prices');
        $count = 0;
        DB::transaction(function () use ($prices, &$count): void {
            foreach ($prices as $extension => $operations) {
                if (! preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*$/', (string) $extension) || ! is_array($operations)) {
                    continue;
                }
                foreach (['register', 'renew', 'transfer', 'redemption'] as $operation) {
                    foreach ($operations[$operation] ?? [] as $period => $entry) {
                        $cost = $entry['promotion'] ?? $entry['price'] ?? null;
                        if (! is_numeric($cost) || $cost <= 0 || $cost != (int) $cost || $period < 1 || $period > 10) {
                            continue;
                        }
                        $product = DomainProduct::firstOrCreate(['extension' => $extension, 'operation' => $operation, 'period' => (int) $period], ['contact_mode' => str_ends_with('.'.$extension, '.ir') ? 'registry_handle' : 'contact']);
                        $product->supplier_price = (int) $cost;
                        $product->synced_at = now();
                        if ($product->automatic_pricing || $product->retail_price === null) {
                            $raw = max((int) ceil($cost * (100 + $product->markup_percent) / 100), $cost + $product->minimum_margin);
                            $product->retail_price = (int) (ceil($raw / 10000) * 10000);
                        }
                        $product->save();
                        $count++;
                    }
                }
            }
            if ($count === 0) {
                throw new RuntimeException('No valid supplier prices returned.');
            }
        });

        return $count;
    }

    public function extension(string $domain): string
    {
        $parts = explode('.', $domain);
        array_shift($parts);

        return implode('.', $parts);
    }

    public function quote(string $domain, string $operation, int $period): array
    {
        $product = DomainProduct::where('extension', $this->extension($domain))->where('operation', $operation)->where('period', $period)->first();
        if (! $product || ! $product->enabled || ! $product->retail_price || ! $product->supplier_price || ! $product->synced_at || $product->synced_at->lt(now()->subDay())
            || $product->retail_price < $product->supplier_price + $product->minimum_margin) {
            throw ValidationException::withMessages(['domain' => 'فروش این پسوند یا دوره فعلاً فعال نیست. لطفاً پسوند یا دوره دیگری انتخاب کنید.']);
        }
        if ($operation === 'register') {
            $renewal = DomainProduct::where('extension', $product->extension)->where('operation', 'renew')->where('period', 1)->first();
            if (! $renewal || ! $renewal->enabled || ! $renewal->retail_price || ! $renewal->supplier_price || ! $renewal->synced_at || $renewal->synced_at->lt(now()->subDay()) || $renewal->retail_price < $renewal->supplier_price + $renewal->minimum_margin) {
                throw ValidationException::withMessages(['domain' => 'قیمت تمدید این پسوند هنوز آماده نیست. لطفاً با پشتیبانی تماس بگیرید.']);
            }
        }
        $currency = AppSetting::currency();
        if (! in_array($currency, ['IRR', 'IRT'], true)) {
            throw ValidationException::withMessages(['domain' => 'واحد پول فروش دامنه هنوز تنظیم نشده است.']);
        }
        $subtotal = $currency === 'IRT' ? (int) ceil($product->retail_price / 10) : $product->retail_price;
        $rate = AppSetting::taxEnabled() ? AppSetting::taxRatePercentage() : 0;
        $tax = (int) round($subtotal * $rate / 100);

        return ['domain_product_id' => $product->id, 'contact_mode' => $product->contact_mode, 'currency' => $currency, 'supplier_price' => $product->supplier_price, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'tax_rate' => $rate, 'total' => $subtotal + $tax, 'quoted_until' => now()->addMinutes(15)];
    }

    public function customerPrice(string $domain): ?array
    {
        try {
            $quote = $this->quote($domain, 'register', 1);
            $renewal = $this->quote($domain, 'renew', 1);
        } catch (ValidationException) {
            return null;
        }
        $wallets = app(WalletService::class);

        return ['total' => $wallets->format($quote['total'], $quote['currency']), 'renewal' => $wallets->format($renewal['total'], $renewal['currency']), 'periods' => DomainProduct::where('extension', $this->extension($domain))->where('operation', 'register')->where('enabled', true)->whereNotNull('retail_price')->orderBy('period')->pluck('period')->all()];
    }
}
