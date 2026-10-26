<?php

namespace App\Services\Domains;

use App\Models\DomainProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IrpowerCatalogService
{
    /** Supplier quotation supplied by the account owner, in toman before VAT. */
    public const QUOTED_ON = '2026-10-26';

    public const PRICES = [
        'com' => [3197000, 3640000], 'net' => [4121000, 4639000], 'me' => [1993000, 7878000],
        'org' => [3538000, 4911000], 'info' => [1261000, 9393000], 'biz' => [6682000, 7517000],
        'mobi' => [2060000, 15087000], 'club' => [6752000, 8095000], 'site' => [342000, 12381000],
        'us' => [3888000, 3888000], 'online' => [743000, 12840000], 'xyz' => [736000, 5562000],
        'co.uk' => [2152000, 3600000], 'nl' => [2102000, 4133000], 'de' => [1767000, 3421000],
        'ca' => [3133000, 4437000], 'eu' => [2191000, 4281000], 'tw' => [9730000, 9730000],
        'io' => [16346000, 25948000], 'ir' => [108000, 108000],
    ];

    public function import(bool $activateExisting = false): int
    {
        return DB::transaction(function () use ($activateExisting): int {
            $created = 0;
            $validUntil = Carbon::parse(self::QUOTED_ON)->addDays(30)->endOfDay();
            foreach (self::PRICES as $extension => [$registration, $renewal]) {
                $operations = ['register' => $registration, 'renew' => $renewal];
                if ($extension !== 'ir') {
                    $operations['transfer'] = $renewal;
                }
                foreach ($operations as $operation => $toman) {
                    $cost = $toman * 10;
                    $previous = DomainProduct::where('provider', 'reseller_world')->where('extension', $extension)->where('operation', $operation)->where('period', 1)->first();
                    $product = DomainProduct::firstOrCreate(['provider' => 'irpower', 'extension' => $extension, 'operation' => $operation, 'period' => 1], [
                        'supplier_price' => $cost, 'retail_price' => (int) (ceil($cost * 1.25 / 10000) * 10000),
                        'minimum_margin' => 0, 'markup_percent' => 25, 'automatic_pricing' => true,
                        'contact_mode' => $extension === 'ir' ? 'registry_handle' : 'contact',
                        'enabled' => $activateExisting && $validUntil->isFuture() && in_array($operation, ['register', 'renew'], true) && ($extension === 'ir' || ($previous?->enabled ?? false)),
                        'synced_at' => now(), 'price_valid_until' => $validUntil,
                    ]);
                    $created += $product->wasRecentlyCreated ? 1 : 0;
                }
            }

            return $created;
        });
    }
}
