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
                        $product = DomainProduct::firstOrCreate(['provider' => 'reseller_world', 'extension' => $extension, 'operation' => $operation, 'period' => (int) $period], ['contact_mode' => str_ends_with('.'.$extension, '.ir') ? 'registry_handle' : 'contact']);
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

    public static function providerLabels(): array
    {
        return ['reseller_world' => 'Reseller.world', 'irpower' => 'IRPOWER'];
    }

    public function providerEnabled(string $provider): bool
    {
        return match ($provider) {
            'reseller_world' => AppSetting::domainResellerToken() !== '' && (bool) AppSetting::getValue('domains.reseller_world_enabled', true),
            'irpower' => AppSetting::irpowerKey() !== '' && (bool) AppSetting::getValue('domains.irpower_enabled', false),
            default => false,
        };
    }

    public function ready(DomainProduct $product): bool
    {
        $fresh = $product->provider === 'irpower'
            ? $product->price_valid_until?->isFuture()
            : $product->synced_at?->gte(now()->subDay());

        return $product->enabled && $fresh && $product->supplier_price > 0 && $product->retail_price > 0
            && $product->retail_price >= $product->supplier_price + $product->minimum_margin
            && ($product->provider === 'irpower' ? AppSetting::irpowerKey() !== '' : AppSetting::domainResellerToken() !== '');
    }

    public function quote(string $domain, string $operation, int $period, ?string $provider = null): array
    {
        $extension = $this->extension($domain);
        $policy = $provider ?? (AppSetting::getValue('domains.provider_routes', [])[$extension] ?? AppSetting::getValue('domains.routing_policy', 'cheapest'));
        $products = DomainProduct::where('extension', $extension)->where('operation', $operation)->where('period', $period)
            ->when(in_array($policy, array_keys(self::providerLabels()), true), fn ($query) => $query->where('provider', $policy))
            ->get()->filter(fn ($product) => $this->ready($product) && ($operation !== 'register' || $this->providerEnabled($product->provider)));
        $products = $products->filter(function ($product) use ($operation): bool {
            return $operation !== 'register' || $this->renewalProduct($product) !== null;
        });
        $product = $products->sortBy(function ($product) use ($policy, $operation): int {
            return $product->retail_price + ($policy === 'two_year' && $operation === 'register' ? $this->renewalProduct($product)->retail_price : 0);
        })->first();
        if (! $product) {
            throw ValidationException::withMessages(['domain' => 'قیمت معتبر ثبت و تمدید این پسوند یا دوره فعلاً آماده نیست. لطفاً پسوند دیگری انتخاب کنید یا با پشتیبانی تماس بگیرید.']);
        }
        $currency = AppSetting::currency();
        if (! in_array($currency, ['IRR', 'IRT'], true)) {
            throw ValidationException::withMessages(['domain' => 'واحد پول فروش دامنه هنوز تنظیم نشده است.']);
        }
        $subtotal = $currency === 'IRT' ? (int) ceil($product->retail_price / 10) : $product->retail_price;
        $rate = AppSetting::taxEnabled() ? AppSetting::taxRatePercentage() : 0;
        $tax = (int) round($subtotal * $rate / 100);

        return ['domain_product_id' => $product->id, 'provider' => $product->provider, 'contact_mode' => $product->contact_mode, 'currency' => $currency, 'supplier_price' => $product->supplier_price, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'tax_rate' => $rate, 'total' => $subtotal + $tax, 'quoted_until' => now()->addMinutes(15)];
    }

    private function renewalProduct(DomainProduct $product): ?DomainProduct
    {
        $renewal = DomainProduct::where('provider', $product->provider)->where('extension', $product->extension)->where('operation', 'renew')->where('period', 1)->first();

        return $renewal && $this->ready($renewal) ? $renewal : null;
    }

    public function renewalPrice(string $domain, string $provider): ?string
    {
        try {
            $quote = $this->quote($domain, 'renew', 1, $provider);
        } catch (ValidationException) {
            return null;
        }

        return app(WalletService::class)->format($quote['total'], $quote['currency']);
    }

    public function customerPrice(string $domain): ?array
    {
        try {
            $quote = $this->quote($domain, 'register', 1);
        } catch (ValidationException) {
            return null;
        }
        $wallets = app(WalletService::class);
        $periods = DomainProduct::where('provider', $quote['provider'])->where('extension', $this->extension($domain))->where('operation', 'register')
            ->orderBy('period')->get()->filter(fn ($product) => $this->ready($product))->pluck('period')->all();

        return ['total' => $wallets->format($quote['total'], $quote['currency']), 'renewal' => $this->renewalPrice($domain, $quote['provider']), 'periods' => array_values($periods)];
    }
}
