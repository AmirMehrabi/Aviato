<?php

namespace App\Services\CloudDns;

use App\Models\DnsZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ZoneSynchronizer
{
    public function __construct(private PowerDnsClient $client) {}

    public function sync(int $zoneId): void
    {
        Cache::lock('cloud-dns:sync:'.$zoneId, 180)->block(1, function () use ($zoneId): void {
            $zone = DB::transaction(function () use ($zoneId): ?DnsZone {
                $zone = DnsZone::query()->lockForUpdate()->find($zoneId);

                return $zone?->load('recordSets');
            });
            if (! $zone || ! $zone->verified_at || $zone->status === 'deleted') {
                return;
            }
            $revision = $zone->revision;
            if ($zone->delete_requested_at || $zone->status === 'suspended') {
                // Remove secondary first, then primary. Ownership stays reserved until both are gone.
                foreach (['secondary', 'primary'] as $node) {
                    $this->client->request($node, 'DELETE', 'zones/'.rawurlencode($zone->fqdn()));
                }
                $changes = ['synced_revision' => $revision, 'synced_at' => now(), 'last_error' => null, 'node_status' => ['primary' => 'removed', 'secondary' => 'removed']];
                if ($zone->delete_requested_at) {
                    $changes += ['status' => 'deleted', 'name' => $zone->uuid.'.deleted.invalid'];
                }
                DnsZone::whereKey($zoneId)->where('revision', $revision)->update($changes);

                return;
            }
            $name = $zone->fqdn();
            $primary = $this->client->zone('primary', $name);
            if (! $primary) {
                $primary = $this->client->request('primary', 'POST', 'zones', [
                    'name' => $name, 'kind' => 'Primary', 'nameservers' => array_map(fn ($ns) => $ns.'.', config('cloud-dns.nameservers')),
                    'soa_edit_api' => 'DEFAULT', 'account' => 'aviato:'.$zone->uuid,
                ])->json();
            }
            $this->client->metadata('primary', $name, 'ALSO-NOTIFY', [config('cloud-dns.secondary_ip')]);
            $this->client->metadata('primary', $name, 'TSIG-ALLOW-AXFR', [config('cloud-dns.tsig_key_name')]);
            $this->client->metadata('primary', $name, 'TSIG-ALLOW-DNSUPDATE', []);
            $secondary = $this->client->zone('secondary', $name);
            if (! $secondary) {
                $this->client->request('secondary', 'POST', 'zones', [
                    'name' => $name, 'kind' => 'Secondary', 'masters' => [config('cloud-dns.primary_ip')], 'account' => 'aviato:'.$zone->uuid,
                ]);
            }
            $this->client->request('secondary', 'PUT', 'zones/'.rawurlencode($name), ['masters' => [config('cloud-dns.primary_ip')], 'slave_tsig_key_ids' => [config('cloud-dns.tsig_key_name').'.']]);
            $desired = $zone->recordSets->map(fn ($r) => [
                'name' => $r->name, 'type' => $r->type, 'ttl' => $r->ttl,
                'records' => array_map(fn ($v) => ['content' => $v, 'disabled' => false], $r->records),
            ])->keyBy(fn ($r) => $r['name'].'|'.$r['type'])->all();
            $desired[$name.'|NS'] = ['name' => $name, 'type' => 'NS', 'ttl' => 3600, 'records' => array_map(fn ($ns) => ['content' => $ns.'.', 'disabled' => false], config('cloud-dns.nameservers'))];
            $existing = collect($primary['rrsets'] ?? [])->keyBy(fn ($r) => $r['name'].'|'.$r['type']);
            $patch = [];
            foreach ($desired as $key => $rrset) {
                if (! isset($existing[$key]) || ! $this->same($existing[$key], $rrset)) {
                    $patch[] = $rrset + ['changetype' => 'REPLACE'];
                }
            }
            foreach ($existing as $key => $rrset) {
                if (! isset($desired[$key]) && ! in_array($rrset['type'], ['SOA', 'DNSKEY', 'RRSIG', 'NSEC', 'NSEC3', 'NSEC3PARAM'], true)) {
                    $patch[] = ['name' => $rrset['name'], 'type' => $rrset['type'], 'changetype' => 'DELETE'];
                }
            }
            if ($patch !== []) {
                $this->client->request('primary', 'PATCH', 'zones/'.rawurlencode($name), ['rrsets' => $patch]);
            }
            $this->client->request('primary', 'PUT', 'zones/'.rawurlencode($name).'/notify');
            $this->client->request('secondary', 'PUT', 'zones/'.rawurlencode($name).'/axfr-retrieve');
            $primary = $this->client->zone('primary', $name);
            $secondary = $this->client->zone('secondary', $name);
            $nodes = ['primary' => $primary['serial'] ?? 0, 'secondary' => $secondary['serial'] ?? 0];
            DnsZone::whereKey($zoneId)->where('revision', $revision)->update(['node_status' => $nodes]);
            if (! $nodes['primary'] || $nodes['primary'] !== $nodes['secondary']) {
                throw new RuntimeException('Waiting for authenticated secondary transfer.');
            }
            DnsZone::whereKey($zoneId)->where('revision', $revision)->whereNull('delete_requested_at')->update([
                'status' => 'ready', 'synced_revision' => $revision, 'synced_at' => now(), 'last_error' => null,
            ]);
        });
    }

    private function same(array $a, array $b): bool
    {
        $normalize = function ($records): array {
            $values = array_map(fn ($r) => $r['content'].'|'.(int) ($r['disabled'] ?? false), $records);
            sort($values);

            return $values;
        };

        return (int) $a['ttl'] === (int) $b['ttl'] && $normalize($a['records']) === $normalize($b['records']);
    }
}
