<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use App\Models\DomainProduct;
use Illuminate\Support\Collection;

class DomainCommerceCatalog
{
    public function extensions(): Collection
    {
        $pricing = app(DomainPricingService::class);
        $routes = AppSetting::getValue('domains.provider_routes', []);
        $defaultPolicy = AppSetting::getValue('domains.routing_policy', 'cheapest');
        $salesEnabled = AppSetting::domainSalesEnabled();
        $providers = collect(DomainPricingService::providerLabels())->mapWithKeys(fn ($label, $provider) => [$provider => $pricing->providerEnabled($provider)]);

        return DomainProduct::whereIn('operation', ['register', 'renew'])->orderBy('extension')->orderBy('provider')->orderBy('period')->get()
            ->groupBy('extension')->map(function ($products, $extension) use ($pricing, $routes, $defaultPolicy, $salesEnabled, $providers) {
                $policy = $routes[$extension] ?? $defaultPolicy;
                $registrations = $products->where('operation', 'register')->where('period', 1);
                $eligible = $registrations->filter(function ($product) use ($products, $pricing, $policy, $providers) {
                    $renewal = $products->where('provider', $product->provider)->where('operation', 'renew')->where('period', 1)->first();

                    return (! in_array($policy, ['reseller_world', 'irpower'], true) || $product->provider === $policy)
                        && $providers[$product->provider] && $pricing->ready($product) && $renewal && $pricing->ready($renewal);
                })->sortBy(function ($product) use ($products, $policy) {
                    $renewal = $products->where('provider', $product->provider)->where('operation', 'renew')->where('period', 1)->first();

                    return $product->retail_price + ($policy === 'two_year' ? $renewal->retail_price : 0);
                });
                $selected = $eligible->first() ?? $registrations->sortByDesc('enabled')->first();
                $renewal = $selected ? $products->where('provider', $selected->provider)->where('operation', 'renew')->where('period', 1)->first() : null;
                $enabled = $products->contains('enabled', true);
                $ready = $salesEnabled && $eligible->isNotEmpty();
                $reason = match (true) {
                    ! $enabled => 'غیرفعال',
                    ! $salesEnabled => 'کلید عمومی فروش خاموش است',
                    $ready => 'آماده فروش',
                    ! $selected => 'قیمت ثبت یک‌ساله موجود نیست',
                    ! $selected->enabled => 'قیمت ثبت یک‌ساله غیرفعال است',
                    ! $providers[$selected->provider] => 'اتصال یا فروش سرویس‌دهنده غیرفعال است',
                    ! $selected->supplier_price || ! $selected->retail_price => 'قیمت ثبت کامل نیست',
                    $selected->retail_price < $selected->supplier_price + $selected->minimum_margin => 'حاشیه سود ثبت کافی نیست',
                    ! $pricing->ready($selected) => 'قیمت تأمین ثبت منقضی یا تأیید نشده است',
                    ! $renewal || ! $pricing->ready($renewal) => 'قیمت تمدید یک‌ساله معتبر نیست',
                    default => 'مسیر سرویس‌دهنده نیازمند بررسی است',
                };

                return compact('extension', 'products', 'selected', 'renewal', 'enabled', 'ready', 'reason', 'policy');
            })->sortBy(fn ($row) => ($row['enabled'] ? '0' : '1').$row['extension']);
    }
}
