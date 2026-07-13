<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VirtualMachineResource;
use App\Models\AppSetting;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\InfrastructureLocation;
use App\Models\Project;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmBundleLocationMapping;
use App\Services\CloudVmProvisioningService;
use App\Services\CustomerVmQuotaService;
use App\Services\IpPoolService;
use App\Services\ProjectAccessService;
use App\Services\VirtualMachineDeletionService;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class VirtualMachineController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly CloudVmProvisioningService $provisioning,
        private readonly VirtualMachineDeletionService $deletions,
        private readonly CustomerVmQuotaService $quota,
        private readonly WalletService $wallets,
        private readonly IpPoolService $ipPools,
    ) {}

    public function options(Request $request, Project $project): JsonResponse
    {
        $customer = $this->customer($request);
        if (! $this->projects->canViewVms($project, $customer)) {
            return $this->error('You do not have VM access to this project.', 'project_forbidden', 403);
        }

        $locations = InfrastructureLocation::query()->with(['proxmoxServer', 'hetznerAccount', 'bundleMappings' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->where('maintenance_mode', false)->orderBy('sort_order')->orderBy('name')->get();
        $images = CloudImage::query()->with(['allowedBundles', 'infrastructureLocation', 'nodeMappings'])->where('is_active', true)->orderBy('sort_order')->get();
        $bundles = VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get();
        $quota = $this->quota->snapshot($project->owner);
        $wallet = $this->wallets->walletFor($project->owner);

        return response()->json([
            'data' => [
                'locations' => $locations->map(fn (InfrastructureLocation $location): array => [
                    'id' => $location->id, 'name' => $location->name, 'provider' => $location->provider,
                    'region' => $location->region, 'country' => $location->country,
                    'bundle_ids' => $location->bundleMappings->pluck('vm_bundle_id')->map(fn ($id) => (int) $id)->values(),
                ])->values(),
                'images' => $images->map(fn (CloudImage $image): array => [
                    'id' => $image->id, 'name' => $image->name, 'os_family' => $image->os_family, 'os_version' => $image->os_version,
                    'default_username' => $image->default_username, 'cloud_init_enabled' => (bool) $image->cloud_init_enabled,
                    'minimums' => ['cpu_cores' => (int) $image->min_cpu_cores, 'ram_gb' => (int) $image->min_ram_gb, 'disk_gb' => (int) $image->min_disk_gb],
                    'location_ids' => $locations->filter(fn ($location) => $this->imageAvailable($image, $location))->pluck('id')->values(),
                    'allowed_bundle_ids' => $image->allowedBundles->pluck('id')->map(fn ($id) => (int) $id)->values(),
                    'available_ip_count' => $this->availableIpCount($image),
                ])->values(),
                'bundles' => $bundles->map(fn (VmBundle $bundle): array => [
                    'id' => $bundle->id, 'name' => $bundle->name, 'cpu_cores' => (int) $bundle->cpu_cores,
                    'ram_gb' => (int) $bundle->ram_gb, 'disk_gb' => (int) $bundle->disk_gb,
                    'monthly_price' => (int) $bundle->monthly_price, 'hourly_price' => $bundle->hourly_price,
                ])->values(),
                'os_families' => $images->groupBy('os_family')->map(fn ($group, $family) => ['key' => $family, 'label' => Str::headline($family), 'versions' => $group->pluck('os_version')->filter()->unique()->values()])->values(),
                'wallet' => ['balance' => (int) $wallet->balance, 'currency' => AppSetting::currency(), 'display_amount' => $this->wallets->format((int) $wallet->balance)],
                'quota' => $quota,
                'can_create' => $this->projects->canManageVms($project, $customer) && $quota['can_create'] && ! $wallet->is_locked,
                'blocking_reason' => $wallet->is_locked ? ($wallet->lock_reason ?: 'Wallet is locked.') : ($quota['message'] ?? null),
            ],
            'meta' => ['request_id' => $request->attributes->get('api_request_id')],
        ]);
    }

    public function index(Request $request, Project $project): AnonymousResourceCollection|JsonResponse
    {
        $customer = $this->customer($request);
        if (! $this->projects->canViewVms($project, $customer)) return $this->error('You do not have VM access to this project.', 'project_forbidden', 403);
        $validator = Validator::make($request->query(), ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['running', 'stopped', 'suspended', 'deleting'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        if ($validator->fails()) return $this->validationError($validator);
        $vms = $this->projects->visibleVms($project, $customer)->with(['project', 'bundle', 'cloudImage', 'infrastructureLocation'])->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('display_name', 'like', '%'.$request->input('search').'%')->orWhere('hostname', 'like', '%'.$request->input('search').'%')->orWhere('ip_address', 'like', '%'.$request->input('search').'%')))->latest()->paginate((int) ($request->input('per_page') ?: 25))->withQueryString();
        return VirtualMachineResource::collection($vms);
    }

    public function show(Request $request, Project $project, VirtualMachine $virtualMachine): VirtualMachineResource|JsonResponse
    {
        $vm = $this->visibleVm($request, $project, $virtualMachine);
        return VirtualMachineResource::make($vm->load(['project', 'bundle', 'cloudImage', 'infrastructureLocation']));
    }

    public function store(Request $request, Project $project): VirtualMachineResource|JsonResponse
    {
        $customer = $this->customer($request);
        if (! $this->projects->canManageVms($project, $customer)) return $this->error('You do not have VM management access to this project.', 'project_forbidden', 403);
        $data = $request->validate([
            'infrastructure_location_id' => ['nullable', 'integer', 'exists:infrastructure_locations,id'], 'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id'], 'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'], 'display_name' => ['nullable', 'string', 'max:128'], 'login_username' => ['nullable', 'string', 'max:64'], 'login_password' => ['nullable', 'string', 'min:8', 'max:255'], 'ssh_public_key' => ['nullable', 'string', 'max:5000'], 'requires_invoice' => ['nullable', 'boolean'], 'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'], 'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'], 'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
        ]);
        $image = CloudImage::query()->with(['allowedBundles', 'infrastructureLocation', 'nodeMappings'])->where('is_active', true)->find($data['cloud_image_id']);
        $location = $image ? $this->resolveLocation($image, $data['infrastructure_location_id'] ?? null) : null;
        $bundle = ! empty($data['vm_bundle_id']) ? VmBundle::query()->where('is_active', true)->find($data['vm_bundle_id']) : null;
        if (! $image || ! $location) return $this->error('The selected image or location is not available.', 'invalid_creation_option', 422);
        if ($data['vm_bundle_id'] ?? false ? ! $bundle : false) return $this->error('The selected bundle is not active.', 'invalid_creation_option', 422);
        if (! $this->imageAvailable($image, $location)) return $this->error('The selected image is not available in this location.', 'image_location_incompatible', 422);
        if (! $this->bundleAvailable($image, $location, (int) ($data['vm_bundle_id'] ?? 0))) return $this->error('The selected bundle is not available for this image and location.', 'bundle_incompatible', 422);
        $quota = $this->quota->snapshot($project->owner); $wallet = $this->wallets->walletFor($project->owner);
        if (! $quota['can_create']) return $this->error($quota['message'] ?: 'VM creation is not available.', 'quota_exceeded', 422);
        if ($wallet->is_locked) return $this->error($wallet->lock_reason ?: 'The project wallet is locked.', 'wallet_locked', 422);
        if ($bundle && $wallet->balance < $this->minimumBalance($bundle, $location)) return $this->error('The project wallet does not meet the minimum creation balance.', 'insufficient_balance', 422);
        if ($location->isProxmox() && $this->availableIpCount($image) < 1) return $this->error('No IP capacity is available for the selected image.', 'ip_capacity_unavailable', 422);
        $data['start_after_create'] = true; $data['onboot'] = false; $data['network_bridge'] = 'vmbr1'; $data['tax_exempt'] = ! ($data['requires_invoice'] ?? false); $data['infrastructure_location_id'] = $location->id;
        try {
            $result = $this->provisioning->create($customer, $data, project: $project);
            $price = $bundle ? $this->effectivePrice($bundle, $location) : 0; $charge = $bundle ? AppSetting::vmCreationChargeAmount($price) : 0;
            if ($charge > 0) $this->wallets->charge($project->owner, $charge, 'VM creation fee '.$result['vm']->display_name, $result['vm'], ['category' => 'vm_creation_fee', 'monthly_price' => $price, 'provider' => $location->provider]);
            $request->attributes->set('api_generated_password', $result['password']);
            return VirtualMachineResource::make($result['vm']->load(['project', 'bundle', 'cloudImage', 'infrastructureLocation']))->response()->setStatusCode(201);
        } catch (ValidationException $e) { return $this->validationError($e->validator); } catch (Throwable $e) { return $this->error('VM creation failed: '.$e->getMessage(), 'creation_failed', 422); }
    }

    public function destroy(Request $request, Project $project, VirtualMachine $virtualMachine): VirtualMachineResource|JsonResponse
    {
        $customer = $this->customer($request); if (! $this->projects->canManageVms($project, $customer)) return $this->error('You do not have VM management access to this project.', 'project_forbidden', 403);
        $vm = $this->visibleVm($request, $project, $virtualMachine, true); $request->validate(['confirmation' => ['required', 'string', Rule::in([$vm->display_name])]]);
        $result = $this->deletions->requestDelete($vm, 'api');
        return VirtualMachineResource::make($result['vm']->load(['project', 'bundle', 'cloudImage', 'infrastructureLocation']))->additional(['meta' => ['request_id' => $request->attributes->get('api_request_id'), 'deletion_status' => $result['status'], 'queued' => $result['queued'], 'finalized' => $result['finalized']]]);
    }

    private function customer(Request $request): Customer { return $request->user('sanctum'); }
    private function visibleVm(Request $request, Project $project, VirtualMachine $vm, bool $manage = false): VirtualMachine { $customer = $this->customer($request); if ($vm->project_id !== $project->id || ! ($manage ? $this->projects->canManageVms($project, $customer) : $this->projects->canViewVms($project, $customer))) abort(404); return $this->projects->visibleVms($project, $customer)->whereKey($vm->id)->firstOrFail(); }
    private function resolveLocation(CloudImage $image, ?int $id): ?InfrastructureLocation { return InfrastructureLocation::query()->with('hetznerAccount')->where('is_active', true)->where('maintenance_mode', false)->when($id, fn ($q) => $q->whereKey($id))->when(! $id && $image->infrastructure_location_id, fn ($q) => $q->whereKey($image->infrastructure_location_id))->when(! $id && ! $image->infrastructure_location_id, fn ($q) => $q->where('proxmox_server_id', $image->proxmox_server_id))->first(); }
    private function imageAvailable(CloudImage $image, InfrastructureLocation $location): bool { return $location->isProxmox() ? $image->isProxmox() && ((int) $image->infrastructure_location_id === (int) $location->id || (int) $image->proxmox_server_id === (int) $location->proxmox_server_id) : $image->isHetzner() && (int) data_get($image->provider_metadata, 'hetzner_account_id') === (int) $location->hetzner_account_id; }
    private function bundleAvailable(CloudImage $image, InfrastructureLocation $location, int $id): bool { return $id <= 0 ? $location->isProxmox() : ($location->isProxmox() ? $image->allowedBundles->contains('id', $id) : VmBundleLocationMapping::query()->where('infrastructure_location_id', $location->id)->where('vm_bundle_id', $id)->where('is_active', true)->whereNotNull('hetzner_server_type_id')->exists()); }
    private function availableIpCount(CloudImage $image): int { if (! $image->isProxmox()) return 999999; $nodes = $image->nodeMappings->where('is_enabled', true)->pluck('node')->filter()->unique(); if ($nodes->isEmpty() && filled($image->node)) $nodes = collect([$image->node]); return $nodes->sum(fn ($node) => $this->ipPools->availableCountFor((int) $image->proxmox_server_id, $node)); }
    private function effectivePrice(VmBundle $bundle, InfrastructureLocation $location): int { if ($location->isProxmox()) return (int) $bundle->monthly_price; $mapping = VmBundleLocationMapping::query()->with('hetznerServerType')->where('vm_bundle_id', $bundle->id)->where('infrastructure_location_id', $location->id)->where('is_active', true)->first(); return (int) AppSetting::convertHetznerUsdToIrr((float) ($mapping?->monthly_price_usd ?? 0)); }
    private function minimumBalance(VmBundle $bundle, InfrastructureLocation $location): int { $price = $this->effectivePrice($bundle, $location); return max((int) ceil($price / 2), AppSetting::vmCreationChargeAmount($price)); }
    private function error(string $message, string $code, int $status): JsonResponse { return response()->json(['error' => ['code' => $code, 'message' => $message], 'meta' => ['request_id' => request()->attributes->get('api_request_id')]], $status); }
    private function validationError($validator): JsonResponse { $errors = $validator instanceof ValidationException ? $validator->errors() : $validator->errors(); return response()->json(['error' => ['code' => 'validation_error', 'message' => 'One or more fields are invalid.', 'fields' => $errors], 'meta' => ['request_id' => request()->attributes->get('api_request_id')]], 422); }
}
