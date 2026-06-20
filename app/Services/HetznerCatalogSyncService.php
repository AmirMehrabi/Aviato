<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CloudImage;
use App\Models\HetznerAccount;
use App\Models\HetznerImage;
use App\Models\HetznerLocation;
use App\Models\HetznerServerType;
use App\Models\InfrastructureLocation;
use App\Models\VmBundleLocationMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HetznerCatalogSyncService
{
    public function __construct(private readonly HetznerCloudService $hetzner) {}

    public function syncAll(): int
    {
        $count = 0;

        HetznerAccount::query()
            ->where('is_active', true)
            ->each(function (HetznerAccount $account) use (&$count): void {
                $this->sync($account);
                $count++;
            });

        return $count;
    }

    public function sync(HetznerAccount $account): void
    {
        $now = now();

        try {
            $locations = $this->hetzner->locations($account);
            $images = $this->hetzner->images($account);
            $serverTypes = $this->hetzner->serverTypes($account);

            DB::transaction(function () use ($account, $locations, $images, $serverTypes, $now): void {
                $locationNames = [];
                foreach ($locations as $location) {
                    $locationNames[] = (string) $location['name'];
                    $infra = InfrastructureLocation::query()->updateOrCreate(
                        [
                            'provider' => InfrastructureLocation::PROVIDER_HETZNER,
                            'hetzner_account_id' => $account->id,
                            'remote_id' => (string) ($location['id'] ?? $location['name']),
                        ],
                        [
                            'name' => (string) ($location['description'] ?? $location['name']),
                            'slug' => 'hetzner-'.$account->id.'-'.Str::slug((string) $location['name']),
                            'region' => (string) $location['name'],
                            'city' => $location['city'] ?? null,
                            'country' => $location['country'] ?? null,
                            'remote_name' => (string) $location['name'],
                            'is_active' => true,
                            'maintenance_mode' => false,
                            'metadata' => $location,
                            'last_synced_at' => $now,
                        ],
                    );

                    HetznerLocation::query()->updateOrCreate(
                        ['hetzner_account_id' => $account->id, 'name' => (string) $location['name']],
                        [
                            'infrastructure_location_id' => $infra->id,
                            'remote_id' => $location['id'] ?? null,
                            'description' => $location['description'] ?? null,
                            'city' => $location['city'] ?? null,
                            'country' => $location['country'] ?? null,
                            'network_zone' => $location['network_zone'] ?? null,
                            'is_active' => true,
                            'raw' => $location,
                            'last_synced_at' => $now,
                        ],
                    );
                }

                HetznerLocation::query()
                    ->where('hetzner_account_id', $account->id)
                    ->whereNotIn('name', $locationNames)
                    ->update(['is_active' => false]);

                $serverTypeIds = [];
                foreach ($serverTypes as $serverType) {
                    $serverTypeIds[] = (int) $serverType['id'];
                    $prices = $serverType['prices'] ?? [];
                    $availableLocations = collect($prices)->pluck('location')->filter()->values()->all();

                    HetznerServerType::query()->updateOrCreate(
                        ['hetzner_account_id' => $account->id, 'remote_id' => (int) $serverType['id']],
                        [
                            'name' => (string) $serverType['name'],
                            'description' => $serverType['description'] ?? null,
                            'architecture' => $serverType['architecture'] ?? null,
                            'cpu_cores' => (int) ($serverType['cores'] ?? 1),
                            'memory_gb' => (float) ($serverType['memory'] ?? 1),
                            'disk_gb' => (int) ($serverType['disk'] ?? 10),
                            'prices' => $prices,
                            'available_locations' => $availableLocations,
                            'deprecated' => (bool) ($serverType['deprecated'] ?? false),
                            'is_active' => ! (bool) ($serverType['deprecated'] ?? false),
                            'raw' => $serverType,
                            'last_synced_at' => $now,
                        ],
                    );
                }

                HetznerServerType::query()
                    ->where('hetzner_account_id', $account->id)
                    ->whereNotIn('remote_id', $serverTypeIds)
                    ->update(['is_active' => false]);

                $imageIds = [];
                foreach ($images as $image) {
                    $deprecated = (bool) ($image['deprecated'] ?? false);
                    $imageIds[] = (int) $image['id'];
                    $cloudImage = $this->syncCloudImage($account, $image, $deprecated);
                    $this->syncHetznerImage($account, $image, $cloudImage, $deprecated, $now);
                }

                HetznerImage::query()
                    ->where('hetzner_account_id', $account->id)
                    ->whereNotIn('remote_id', $imageIds)
                    ->update(['is_active' => false]);

                $this->refreshMappingPrices($account);

                $account->forceFill([
                    'connection_status' => HetznerAccount::CONNECTION_ONLINE,
                    'sync_status' => HetznerAccount::SYNC_SYNCED,
                    'sync_error' => null,
                    'synced_at' => $now,
                    'last_seen_at' => $now,
                    'remote_inventory' => [
                        'locations' => count($locations),
                        'images' => count($images),
                        'server_types' => count($serverTypes),
                    ],
                ])->save();
            });
        } catch (Throwable $exception) {
            $account->forceFill([
                'connection_status' => HetznerAccount::CONNECTION_OFFLINE,
                'sync_status' => HetznerAccount::SYNC_FAILED,
                'sync_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function syncCloudImage(HetznerAccount $account, array $image, bool $deprecated): CloudImage
    {
        $name = (string) ($image['description'] ?? $image['name']);
        $remoteImageId = (string) $image['id'];
        $slug = 'hetzner-'.$account->id.'-'.$remoteImageId;
        $osFamily = $this->osFamily((string) ($image['os_flavor'] ?? $image['name']));

        $cloudImage = CloudImage::query()
            ->where('slug', $slug)
            ->first();

        if (! $cloudImage) {
            $cloudImage = CloudImage::query()
                ->where('provider', InfrastructureLocation::PROVIDER_HETZNER)
                ->where('remote_image_id', $remoteImageId)
                ->first();
        }

        $cloudImage ??= new CloudImage;

        $cloudImage->forceFill([
            'provider' => InfrastructureLocation::PROVIDER_HETZNER,
            'name' => $name,
            'slug' => $slug,
            'description' => $image['description'] ?? null,
            'os_family' => $osFamily,
            'os_version' => $image['os_version'] ?? $image['name'],
            'logo_key' => $osFamily,
            'node' => 'hetzner',
            'template_vmid' => 0,
            'remote_image_id' => $remoteImageId,
            'default_username' => 'root',
            'storage' => null,
            'disk_device' => 'local',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => true,
            'remote_architecture' => $image['architecture'] ?? null,
            'provider_metadata' => [
                'hetzner_account_id' => $account->id,
                'remote_image_id' => $remoteImageId,
                'remote_name' => $image['name'] ?? null,
                'type' => $image['type'] ?? null,
            ],
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => ! $deprecated,
            'sort_order' => 100,
        ])->save();

        CloudImage::query()
            ->where('provider', InfrastructureLocation::PROVIDER_HETZNER)
            ->where('remote_image_id', $remoteImageId)
            ->where('id', '!=', $cloudImage->id)
            ->delete();

        return $cloudImage->refresh();
    }

    private function syncHetznerImage(HetznerAccount $account, array $image, CloudImage $cloudImage, bool $deprecated, mixed $now): HetznerImage
    {
        $remoteImageId = (int) $image['id'];

        $hetznerImage = HetznerImage::query()
            ->where('hetzner_account_id', $account->id)
            ->where('remote_id', $remoteImageId)
            ->first();

        $hetznerImage ??= new HetznerImage;

        $hetznerImage->forceFill([
            'hetzner_account_id' => $account->id,
            'cloud_image_id' => $cloudImage->id,
            'remote_id' => $remoteImageId,
            'name' => (string) $image['name'],
            'description' => $image['description'] ?? null,
            'type' => $image['type'] ?? null,
            'architecture' => $image['architecture'] ?? null,
            'os_flavor' => $image['os_flavor'] ?? null,
            'os_version' => $image['os_version'] ?? null,
            'deprecated' => $deprecated,
            'is_active' => ! $deprecated,
            'raw' => $image,
            'last_synced_at' => $now,
        ])->save();

        HetznerImage::query()
            ->where('hetzner_account_id', $account->id)
            ->where('remote_id', $remoteImageId)
            ->where('id', '!=', $hetznerImage->id)
            ->delete();

        return $hetznerImage->refresh();
    }

    private function refreshMappingPrices(HetznerAccount $account): void
    {
        $rate = AppSetting::hetznerUsdToIrrRate();

        VmBundleLocationMapping::query()
            ->whereHas('location', fn ($query) => $query->where('hetzner_account_id', $account->id))
            ->with(['location', 'hetznerServerType'])
            ->get()
            ->each(function (VmBundleLocationMapping $mapping) use ($rate): void {
                $serverType = $mapping->hetznerServerType;
                $locationName = $mapping->location?->remote_name;
                $usd = $serverType?->monthlyUsdForLocation($locationName);

                if ($usd === null) {
                    return;
                }

                $mapping->forceFill([
                    'monthly_price_usd' => $usd,
                    'monthly_price_irr' => AppSetting::convertHetznerUsdToIrr($usd),
                    'usd_to_irr_rate' => $rate,
                    'price_synced_at' => now(),
                ])->save();
            });
    }

    private function osFamily(string $value): string
    {
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'ubuntu') => 'ubuntu',
            str_contains($value, 'debian') => 'debian',
            str_contains($value, 'rocky') => 'rocky',
            str_contains($value, 'windows') => 'windows',
            default => 'ubuntu',
        };
    }
}
