<?php

namespace App\Services;

use App\Models\VirtualMachine;
use RuntimeException;

class WalletNetworkRestrictionService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly HetznerCloudService $hetzner,
    ) {}

    public function freeze(VirtualMachine $vm, array $state): void
    {
        if ($vm->isHetzner()) {
            $this->freezeHetzner($vm, $state);

            return;
        }

        $this->assertProxmox($vm);
        $config = $this->proxmoxConfig($vm);
        if (! isset($state['network_interfaces'])) {
            $interfaces = [];
            $found = false;
            foreach ($config as $key => $device) {
                if (preg_match('/^net\d+$/', (string) $key)) {
                    $found = true;
                    if (! preg_match('/(?:^|,)link_down=1(?:,|$)/', (string) $device)) {
                        $interfaces[] = $key;
                    }
                }
            }
            if (! $found) {
                throw new RuntimeException('No Proxmox VM network interface was found.');
            }
            $state['network_interfaces'] = $interfaces;
            $this->save($vm, $state);
        }
        foreach ($state['network_interfaces'] as $interface) {
            if (preg_match('/(?:^|,)link_down=1(?:,|$)/', (string) ($config[$interface] ?? ''))) {
                continue;
            }
            $result = $this->setProxmoxLink($vm, false, $interface);
            $this->waitProxmox($vm, $result);
        }
        $config = $this->proxmoxConfig($vm);
        foreach ($state['network_interfaces'] as $interface) {
            if (! preg_match('/(?:^|,)link_down=1(?:,|$)/', (string) ($config[$interface] ?? ''))) {
                throw new RuntimeException("Proxmox did not confirm network isolation for {$interface}.");
            }
        }
        $state['network_frozen_at'] ??= now()->toISOString();
        unset($state['last_error'], $state['last_error_at']);
        $this->save($vm, $state);
    }

    public function restore(VirtualMachine $vm, array $state): void
    {
        if ($vm->isHetzner()) {
            $this->restoreHetzner($vm, $state);

            return;
        }
        if (empty($state['network_interfaces'])) {
            return;
        }
        $this->assertProxmox($vm);
        $config = $this->proxmoxConfig($vm);
        foreach ($state['network_interfaces'] as $interface) {
            if (! preg_match('/(?:^|,)link_down=1(?:,|$)/', (string) ($config[$interface] ?? ''))) {
                continue;
            }
            $result = $this->setProxmoxLink($vm, true, $interface);
            $this->waitProxmox($vm, $result);
        }
        $config = $this->proxmoxConfig($vm);
        foreach ($state['network_interfaces'] as $interface) {
            if (! isset($config[$interface]) || preg_match('/(?:^|,)link_down=1(?:,|$)/', (string) $config[$interface])) {
                throw new RuntimeException("Proxmox did not confirm network restoration for {$interface}.");
            }
        }
    }

    private function freezeHetzner(VirtualMachine $vm, array $state): void
    {
        $account = $vm->infrastructureLocation?->hetznerAccount;
        if (! $account || ! $vm->remote_id) {
            throw new RuntimeException('Hetzner server is missing provider credentials or ID.');
        }
        $server = $this->hetzner->server($account, $vm->remote_id);
        if (! $server) {
            throw new RuntimeException('Hetzner server was not found.');
        }
        if (! isset($state['hetzner_private_nets'])) {
            $state['hetzner_private_nets'] = array_map(static fn (array $net): array => [
                'network' => (int) $net['network'],
                'ip' => $net['ip'] ?? null,
                'alias_ips' => $net['alias_ips'] ?? [],
            ], $server['private_net'] ?? []);
            $state['hetzner_public'] = ! empty(data_get($server, 'public_net.ipv4')) || ! empty(data_get($server, 'public_net.ipv6'));
            $this->save($vm, $state);
        }
        if ($state['hetzner_public']) {
            $firewallId = $this->hetzner->walletFreezeFirewall($account);
            $state['hetzner_firewall_id'] = $firewallId;
            $this->save($vm, $state);
            if (! $this->hetzner->firewallAppliedToServer($account, $firewallId, $vm->remote_id)) {
                $result = $this->hetzner->applyFirewall($account, $firewallId, $vm->remote_id);
                $this->waitHetzner($account, data_get($result, 'actions.0.id'));
            }
            if (! $this->hetzner->firewallAppliedToServer($account, $firewallId, $vm->remote_id)) {
                throw new RuntimeException('Hetzner did not confirm the wallet freeze firewall.');
            }
        }
        foreach ($state['hetzner_private_nets'] as $network) {
            $server = $this->hetzner->server($account, $vm->remote_id);
            if (! collect($server['private_net'] ?? [])->contains('network', $network['network'])) {
                continue;
            }
            $result = $this->hetzner->detachFromNetwork($account, $vm->remote_id, $network['network']);
            $this->waitHetzner($account, data_get($result, 'action.id'));
        }
        $server = $this->hetzner->server($account, $vm->remote_id);
        foreach ($state['hetzner_private_nets'] as $network) {
            if (collect($server['private_net'] ?? [])->contains('network', $network['network'])) {
                throw new RuntimeException('Hetzner did not confirm private-network detachment.');
            }
        }
        $state['network_frozen_at'] ??= now()->toISOString();
        unset($state['last_error'], $state['last_error_at']);
        $this->save($vm, $state);
    }

    private function restoreHetzner(VirtualMachine $vm, array $state): void
    {
        $account = $vm->infrastructureLocation?->hetznerAccount;
        if (! $account || ! $vm->remote_id) {
            throw new RuntimeException('Hetzner server is missing provider credentials or ID.');
        }
        foreach ($state['hetzner_private_nets'] ?? [] as $network) {
            $server = $this->hetzner->server($account, $vm->remote_id);
            if (collect($server['private_net'] ?? [])->contains('network', $network['network'])) {
                continue;
            }
            $result = $this->hetzner->attachToNetwork($account, $vm->remote_id, $network);
            $this->waitHetzner($account, data_get($result, 'action.id'));
        }
        $server = $this->hetzner->server($account, $vm->remote_id);
        foreach ($state['hetzner_private_nets'] ?? [] as $network) {
            if (! collect($server['private_net'] ?? [])->contains('network', $network['network'])) {
                throw new RuntimeException('Hetzner did not confirm private-network restoration.');
            }
        }
        if (! empty($state['hetzner_firewall_id'])) {
            if ($this->hetzner->firewallAppliedToServer($account, $state['hetzner_firewall_id'], $vm->remote_id)) {
                $result = $this->hetzner->removeFirewall($account, $state['hetzner_firewall_id'], $vm->remote_id);
                $this->waitHetzner($account, data_get($result, 'actions.0.id'));
            }
            if ($this->hetzner->firewallAppliedToServer($account, $state['hetzner_firewall_id'], $vm->remote_id)) {
                throw new RuntimeException('Hetzner did not confirm removal of the wallet freeze firewall.');
            }
        }
    }

    private function assertProxmox(VirtualMachine $vm): void
    {
        if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            throw new RuntimeException('Proxmox guest details are missing.');
        }
    }

    private function proxmoxConfig(VirtualMachine $vm): array
    {
        return $vm->isLxc()
            ? $this->proxmox->lxcConfig($vm->proxmoxServer, $vm->node, $vm->vmid)
            : $this->proxmox->vmConfig($vm->proxmoxServer, $vm->node, $vm->vmid);
    }

    private function setProxmoxLink(VirtualMachine $vm, bool $enabled, string $interface): array
    {
        return $vm->isLxc()
            ? $this->proxmox->setLxcNetworkLinkState($vm->proxmoxServer, $vm->node, $vm->vmid, $enabled, $interface)
            : $this->proxmox->setVmNetworkLinkState($vm->proxmoxServer, $vm->node, $vm->vmid, $enabled, $interface);
    }

    private function waitProxmox(VirtualMachine $vm, array $result): void
    {
        if (filled($result['task_id'] ?? null)) {
            $this->proxmox->waitForTask($vm->proxmoxServer, $vm->node, $result['task_id'], 180);
        }
    }

    private function save(VirtualMachine $vm, array $state): void
    {
        $vm->forceFill(['wallet_restriction' => $state])->save();
    }

    private function waitHetzner($account, mixed $actionId): void
    {
        if (! $actionId) {
            throw new RuntimeException('Hetzner did not return an action ID for the network change.');
        }
        $this->hetzner->waitForAction($account, $actionId, 180);
    }
}
