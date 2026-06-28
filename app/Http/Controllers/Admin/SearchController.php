<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = "%{$query}%";
        $limit = 8;

        $results = [];

        $customers = Customer::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('id', 'like', $like))
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone', 'status']);

        if ($customers->isNotEmpty()) {
            $results[] = [
                'label' => 'مشتریان',
                'icon' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0',
                'items' => $customers->map(fn (Customer $c) => [
                    'title' => $c->name,
                    'subtitle' => $c->email ?: $c->phone,
                    'url' => route('admin.customers.show', $c),
                    'badge' => $c->status === 'suspended' ? 'تعلیق' : null,
                    'badgeClass' => $c->status === 'suspended' ? 'bg-red-50 text-red-700' : '',
                ])->toArray(),
            ];
        }

        $servers = ProxmoxServer::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('cluster_name', 'like', $like)
                ->orWhere('host', 'like', $like)
                ->orWhere('datacenter', 'like', $like))
            ->limit($limit)
            ->get(['id', 'name', 'cluster_name', 'host', 'datacenter', 'connection_status']);

        if ($servers->isNotEmpty()) {
            $results[] = [
                'label' => 'سرورهای Proxmox',
                'icon' => 'M5 6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4H5V6Zm0 4h14v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-8Zm3 4h2m4 0h2M8 17h8',
                'items' => $servers->map(fn (ProxmoxServer $s) => [
                    'title' => $s->name,
                    'subtitle' => $s->host.' · '.$s->datacenter,
                    'url' => route('admin.proxmox-servers.show', $s),
                    'badge' => $s->connection_status === 'online' ? 'آنلاین' : ($s->connection_status === 'offline' ? 'آفلاین' : null),
                    'badgeClass' => $s->connection_status === 'online' ? 'bg-emerald-50 text-emerald-700' : ($s->connection_status === 'offline' ? 'bg-red-50 text-red-700' : ''),
                ])->toArray(),
            ];
        }

        $vms = VirtualMachine::query()
            ->notDeleted()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('ip_address', 'like', $like)
                ->orWhere('hostname', 'like', $like)
                ->orWhere('vmid', 'like', $like))
            ->limit($limit)
            ->get(['id', 'uuid', 'name', 'ip_address', 'status', 'cpu_cores', 'ram_gb', 'disk_gb']);

        if ($vms->isNotEmpty()) {
            $results[] = [
                'label' => 'ماشین‌های مجازی',
                'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6',
                'items' => $vms->map(fn (VirtualMachine $vm) => [
                    'title' => $vm->name,
                    'subtitle' => ($vm->ip_address ?: 'no-ip').' · '.$vm->cpu_cores.'C/'.$vm->ram_gb.'G/'.$vm->disk_gb.'G',
                    'url' => route('admin.virtual-machines.show', $vm),
                    'badge' => $vm->status === 'running' ? 'روشن' : ($vm->status === 'stopped' ? 'خاموش' : 'تعلیق'),
                    'badgeClass' => $vm->status === 'running' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-600',
                ])->toArray(),
            ];
        }

        $pools = IpPool::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('node', 'like', $like)
                ->orWhere('gateway', 'like', $like)
                ->orWhere('start_ip', 'like', $like))
            ->limit($limit)
            ->get(['id', 'name', 'node', 'start_ip', 'end_ip', 'is_active']);

        if ($pools->isNotEmpty()) {
            $results[] = [
                'label' => 'IP Pools',
                'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4M4.9 16.9l2.8-2.8m8.6-8.6 2.8-2.8',
                'items' => $pools->map(fn (IpPool $p) => [
                    'title' => $p->name,
                    'subtitle' => $p->start_ip.' – '.$p->end_ip.' · '.$p->node,
                    'url' => route('admin.ip-pools.show', $p),
                    'badge' => $p->is_active ? 'فعال' : 'غیرفعال',
                    'badgeClass' => $p->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500',
                ])->toArray(),
            ];
        }

        $images = CloudImage::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('os_family', 'like', $like)
                ->orWhere('os_version', 'like', $like))
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'os_family', 'os_version', 'is_active']);

        if ($images->isNotEmpty()) {
            $results[] = [
                'label' => 'Cloud Images',
                'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z',
                'items' => $images->map(fn (CloudImage $i) => [
                    'title' => $i->name,
                    'subtitle' => ucfirst((string) $i->os_family).' '.$i->os_version.' · '.$i->slug,
                    'url' => route('admin.cloud-images.edit', $i),
                    'badge' => $i->is_active ? 'فعال' : null,
                    'badgeClass' => $i->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : '',
                ])->toArray(),
            ];
        }

        return response()->json(['groups' => $results]);
    }
}
