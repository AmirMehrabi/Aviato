<?php

namespace App\Services;

use App\Jobs\ApplyVmUpgradeJob;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmConfigurationChange;
use App\Models\VmUpgradeOrder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VmHardwareService
{
    public function __construct(private readonly ProxmoxService $proxmox, private readonly BillingService $billing) {}

    public function assertAvailable(VirtualMachine $vm): void
    {
        if ($vm->isActionLocked() || $vm->provisioning_status !== VirtualMachine::PROVISION_READY || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
            $this->invalid('ابتدا منتظر پایان عملیات جاری بمانید. سرور باید آماده و خارج از تعلیق باشد.');
        }
    }

    public function observe(VirtualMachine $vm): array
    {
        if (! $vm->isProxmox() || $vm->isLxc() || ! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            $this->invalid('مدیریت مستقل سخت‌افزار فعلاً برای ماشین‌های QEMU متصل به Proxmox در دسترس است.');
        }
        $vm->forceFill(['hardware_attempted_at' => now()])->save();
        try {
            $config = $this->proxmox->vmEffectiveConfig($vm->proxmoxServer, $vm->node, $vm->vmid);
            $pending = $this->proxmox->vmPendingConfig($vm->proxmoxServer, $vm->node, $vm->vmid);
            $status = $this->proxmox->vmStatus($vm->proxmoxServer, $vm->node, $vm->vmid);
            if (! in_array($status['status'] ?? null, ['running', 'stopped'], true)) {
                $this->invalid('وضعیت روشن یا خاموش بودن سرور قابل تأیید نیست. دوباره بررسی کنید.');
            }
            $observation = $this->normalize($vm, $config);
            $observation['status'] = $status['status'];
            $observation['pending'] = array_values(array_filter($pending, fn ($item) => array_key_exists('pending', $item) || ! empty($item['delete'])));
            $observation['locked'] = ! empty($config['lock']);
            $observation['identity'] = ['name' => $config['name'] ?? null, 'smbios1' => $config['smbios1'] ?? null];
            $observation['digest'] = $config['digest'] ?? hash('sha256', json_encode($config));
            $observation['checked_at'] = now()->toIso8601String();
            DB::transaction(function () use ($vm, $observation): void {
                $locked = VirtualMachine::query()->lockForUpdate()->findOrFail($vm->id);
                $state = $locked->remote_state ?? [];
                if (empty($state['hardware_identity']) && ! $locked->isActionLocked()
                    && ($observation['identity']['name'] ?? null) === ($locked->remote_name ?: $locked->name)) {
                    $state['hardware_identity'] = $observation;
                }
                $locked->forceFill([
                    'hardware_drift' => $observation['cpu_cores'] !== $locked->cpu_cores
                        || $observation['ram_mb'] !== $locked->ram_gb * 1024 || $observation['disk_bytes'] !== $locked->disk_gb * 1024 ** 3,
                    'hardware_observation' => $observation, 'hardware_checked_at' => now(),
                    'hardware_sync_error' => null, 'remote_state' => $state,
                ])->save();
            });
            $vm->refresh();

            return $observation;
        } catch (Throwable $exception) {
            $vm->forceFill(['hardware_sync_error' => 'ارتباط یا خواندن سخت‌افزار تأیید نشد. اطلاعات قبلی حفظ شده است.'])->save();
            throw $exception;
        }
    }

    public function normalize(VirtualMachine $vm, array $config): array
    {
        $sockets = max(1, (int) ($config['sockets'] ?? 1));
        $cores = (int) ($config['cores'] ?? 0);
        $memory = (int) ($config['memory'] ?? 0);
        $disks = [];
        foreach ($config as $device => $value) {
            if (! preg_match('/^(scsi|virtio|sata|ide)\d+$/', $device) || ! is_string($value)
                || str_contains($value, 'media=cdrom') || str_contains($value, 'cloudinit')) {
                continue;
            }
            $bytes = $this->diskBytes($value);
            $disks[$device] = ['volume' => explode(',', $value)[0], 'size_bytes' => $bytes];
        }
        $primary = (string) data_get($vm->desired_state, 'disk_device', $vm->cloudImage?->disk_device ?: 'scsi0');
        if ($cores < 1 || $memory < 1 || ! isset($disks[$primary]['size_bytes'])) {
            $this->invalid('پردازنده، حافظه یا دیسک اصلی به‌طور کامل شناسایی نشد. تنظیمات و دستگاه دیسک اصلی را بررسی کنید.');
        }

        return ['cpu_cores' => min($cores * $sockets, (int) ($config['vcpus'] ?? $cores * $sockets)),
            'ram_mb' => $memory, 'disk_bytes' => $disks[$primary]['size_bytes'], 'sockets' => $sockets,
            'vcpus' => $config['vcpus'] ?? null, 'primary_device' => $primary, 'disks' => $disks];
    }

    public function diskBytes(string $value): ?int
    {
        if (! preg_match('/(?:^|,)size=(\d+(?:\.\d+)?)([KMGT]?)(?:i?B)?(?:,|$)/i', $value, $m)) {
            return null;
        }

        return (int) round((float) $m[1] * 1024 ** (array_search(strtoupper($m[2]), ['', 'K', 'M', 'G', 'T'], true)));
    }

    public function snapshot(VirtualMachine $vm): array
    {
        return array_merge($vm->pricingSnapshot(), ['cpu_cores' => $vm->cpu_cores, 'ram_gb' => $vm->ram_gb, 'disk_gb' => $vm->disk_gb,
            'ip_count' => $vm->ip_count, 'vm_bundle_id' => $vm->vm_bundle_id, 'customer_id' => $vm->customer_id, 'project_id' => $vm->project_id,
            'status' => $vm->status, 'tax_exempt' => $vm->tax_exempt, 'node' => $vm->node, 'vmid' => $vm->vmid,
            'proxmox_server_id' => $vm->proxmox_server_id]);
    }

    public function prices(VirtualMachine $vm): array
    {
        return ['running' => (int) round($this->billing->hourlyWhenRunning($vm) * 730),
            'stopped' => (int) round($this->billing->persistentHourly($vm) * 730)];
    }

    public function quote(VirtualMachine $vm, User $actor, string $kind, array $input): array
    {
        $this->assertAvailable($vm);
        $before = $this->snapshot($vm);
        $target = clone $vm;
        $remote = null;
        if ($kind === 'pricing') {
            if (($input['pricing_mode'] ?? '') === 'resources' && ! $vm->isProxmox()) {
                $this->invalid('قیمت‌گذاری منابع مستقل برای این ارائه‌دهنده پشتیبانی نمی‌شود.');
            }
            if (($input['pricing_mode'] ?? '') === 'inherit' && ! $vm->bundle && ! $vm->isHetzner()) {
                $this->invalid('این سرور پلن ندارد. قیمت اختصاصی یا نرخ منابع را انتخاب کنید.');
            }
            $target->fill(['pricing_mode' => $input['pricing_mode'],
                'custom_monthly_price' => $input['pricing_mode'] === 'custom' ? $input['custom_monthly_price'] : null,
                'custom_stopped_monthly_price' => $input['pricing_mode'] === 'custom' ? $input['custom_stopped_monthly_price'] : null]);
        } else {
            $remote = $this->observe($vm);
            $this->assertRemoteEditable($vm, $remote);
            if ($kind === 'sync') {
                if ($remote['ram_mb'] % 1024 || $remote['disk_bytes'] % (1024 ** 3)) {
                    $this->invalid('برای پذیرش، حافظه و دیسک باید مضرب کامل GiB باشند. مقادیر دقیق شناسایی‌شده در جدول نمایش داده می‌شوند.');
                }
                $target->fill(['cpu_cores' => $remote['cpu_cores'], 'ram_gb' => intdiv($remote['ram_mb'], 1024), 'disk_gb' => intdiv($remote['disk_bytes'], 1024 ** 3), 'status' => $remote['status']]);
            } else {
                if ($remote['cpu_cores'] !== $vm->cpu_cores || $remote['ram_mb'] !== $vm->ram_gb * 1024 || $remote['disk_bytes'] !== $vm->disk_gb * 1024 ** 3) {
                    $this->invalid('منابع Proxmox با پنل متفاوت است. ابتدا تغییرات فعلی را با «پذیرش سخت‌افزار» همگام کنید.');
                }
                if ($remote['disk_bytes'] > $input['disk_gb'] * 1024 ** 3) {
                    $this->invalid('کوچک کردن دیسک پشتیبانی نمی‌شود. برای کاهش ظرفیت از مهاجرت به دیسک جدید استفاده کنید.');
                }
                if ($remote['vcpus'] !== null || $input['cpu_cores'] % $remote['sockets']) {
                    $this->invalid('تنظیم CPU فعلی نیازمند مدیریت توپولوژی در Proxmox است؛ سپس از همگام‌سازی استفاده کنید.');
                }
                if ($input['ram_gb'] > $vm->ram_gb || $input['disk_gb'] > $vm->disk_gb) {
                    $storage = explode(':', $remote['disks'][$remote['primary_device']]['volume'])[0];
                    $capacity = $this->proxmox->hardwareCapacity($vm->proxmoxServer, $vm->node, $storage);
                    $memoryIncrease = max(0, $input['ram_gb'] - $vm->ram_gb) * 1024 ** 3;
                    $diskIncrease = max(0, $input['disk_gb'] - $vm->disk_gb) * 1024 ** 3;
                    if (($memoryIncrease && (! isset($capacity['memory_free']) || $memoryIncrease > $capacity['memory_free']))
                        || ($diskIncrease && (! isset($capacity['storage_available']) || $diskIncrease > $capacity['storage_available']))) {
                        $this->invalid('ظرفیت آزاد حافظه یا فضای ذخیره‌سازی برای این افزایش کافی یا قابل تأیید نیست. منابع کمتری انتخاب کنید یا ظرفیت نود را بررسی کنید.');
                    }
                }
                $target->fill(array_intersect_key($input, array_flip(['cpu_cores', 'ram_gb', 'disk_gb'])));
            }
        }
        if ($kind === 'hardware' && $vm->cloudImage && ($target->cpu_cores < $vm->cloudImage->min_cpu_cores
            || $target->ram_gb < $vm->cloudImage->min_ram_gb || $target->disk_gb < $vm->cloudImage->min_disk_gb)) {
            $this->invalid('منابع انتخاب‌شده کمتر از حداقل مورد نیاز سیستم‌عامل است.');
        }
        $after = $this->snapshot($target);
        if ($before === $after) {
            $this->invalid('تغییری انتخاب نشده است.');
        }
        $quote = ['request_key' => (string) Str::uuid(), 'vm_id' => $vm->id, 'actor_id' => $actor->id,
            'kind' => $kind, 'reason' => $input['reason'], 'before' => $before, 'after' => $after,
            'before_prices' => $this->prices($vm), 'after_prices' => $this->prices($target),
            'remote' => $remote, 'expires_at' => now()->addMinutes(10)->timestamp];
        $quote['token'] = Crypt::encryptString(json_encode($quote));

        return $quote;
    }

    private function assertRemoteEditable(VirtualMachine $vm, array $remote): void
    {
        if ($remote['pending'] || $remote['locked']) {
            $this->invalid('سرور قفل است یا تغییرات در انتظار راه‌اندازی مجدد دارد. ابتدا آن‌ها را در Proxmox تعیین تکلیف کنید.');
        }
        // Refuse a replaced primary volume or guest identity, rather than silently importing a reused VMID.
        if (! empty($remote['identity']['name']) && $remote['identity']['name'] !== ($vm->remote_name ?: $vm->name)) {
            $this->invalid('نام ماشین در Proxmox با نگاشت پنل یکسان نیست. ابتدا هویت ماشین را بررسی کنید.');
        }
        $accepted = data_get($vm->remote_state, 'accepted_hardware') ?? data_get($vm->remote_state, 'hardware_identity');
        if ($accepted && (($accepted['identity'] ?? null) !== $remote['identity']
            || ($accepted['disks'][$remote['primary_device']]['volume'] ?? null) !== $remote['disks'][$remote['primary_device']]['volume'])) {
            $this->invalid('هویت سرور یا دیسک اصلی تغییر کرده است. پیش از همگام‌سازی، نگاشت این ماشین را در زیرساخت بررسی کنید.');
        }
    }

    public function confirm(VirtualMachine $vm, User $actor, string $token): ?VmUpgradeOrder
    {
        try {
            if (base64_encode(base64_decode($token, true)) !== $token) {
                throw new \RuntimeException('Invalid token encoding.');
            }
            $quote = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->invalid('پیش‌نمایش معتبر نیست. دوباره تغییرات را بررسی کنید.');
        }
        if (($quote['vm_id'] ?? null) !== $vm->id || ($quote['actor_id'] ?? null) !== $actor->id || ($quote['expires_at'] ?? 0) < now()->timestamp) {
            $this->invalid('پیش‌نمایش منقضی شده است. دوباره تغییرات را بررسی کنید.');
        }
        abort_unless($actor->allows($quote['kind'] === 'pricing' ? 'pricing.manage' : 'virtual-machines.manage'), 403);
        if ($quote['kind'] === 'hardware') {
            abort_unless($actor->allows('virtual-machines.power'), 403);
        }
        if ($vm->configurationChanges()->where('request_key', $quote['request_key'])->exists()) {
            return null;
        }
        $remote = $quote['kind'] !== 'pricing' ? $this->observe($vm) : null;
        if ($remote && ($remote['digest'] !== $quote['remote']['digest'] || $remote['status'] !== $quote['remote']['status'] || $remote['pending'] || $remote['locked'])) {
            $this->invalid('تنظیمات Proxmox از زمان پیش‌نمایش تغییر کرده است. پیش‌نمایش تازه بگیرید.');
        }

        return DB::transaction(function () use ($vm, $actor, $quote, $remote): ?VmUpgradeOrder {
            $vm = VirtualMachine::query()->with(['bundle', 'customer', 'project.owner'])->lockForUpdate()->findOrFail($vm->id);
            if ($vm->configurationChanges()->where('request_key', $quote['request_key'])->exists()) {
                return null;
            }
            $this->assertAvailable($vm);
            if ($this->snapshot($vm) !== $quote['before'] || $this->prices($vm) !== $quote['before_prices']) {
                $this->invalid('اطلاعات یا قیمت سرور تغییر کرده است. پیش‌نمایش تازه بگیرید.');
            }
            $target = clone $vm;
            $target->fill(array_intersect_key($quote['after'], array_flip(['pricing_mode', 'custom_monthly_price', 'custom_stopped_monthly_price', 'cpu_cores', 'ram_gb', 'disk_gb', 'ip_count', 'vm_bundle_id', 'status'])));
            if ($this->prices($target) !== $quote['after_prices']) {
                $this->invalid('نرخ قیمت‌گذاری تغییر کرده است. پیش‌نمایش تازه بگیرید.');
            }
            if ($quote['kind'] === 'hardware') {
                $order = VmUpgradeOrder::create([
                    'customer_id' => $vm->project?->owner_customer_id ?? $vm->customer_id,
                    'virtual_machine_id' => $vm->id, 'type' => VmUpgradeOrder::TYPE_HARDWARE,
                    'status' => VmUpgradeOrder::STATUS_PENDING, 'before_snapshot' => $quote['before'],
                    'after_snapshot' => array_merge($quote['after'], ['expected_digest' => $remote['digest'],
                        'original_status' => $remote['status'], 'sockets' => $remote['sockets'],
                        'request_key' => $quote['request_key'], 'reason' => $quote['reason']]),
                ]);
                $order->virtualMachine->operations()->where('details->upgrade_order_id', $order->id)->update(['actor_type' => 'admin', 'actor_id' => $actor->id]);
                ApplyVmUpgradeJob::dispatch($order->id)->onQueue(ApplyVmUpgradeJob::QUEUE)->afterCommit();
            } else {
                $order = null;
                $at = now();
                app(UsageBillingService::class)->accrueVm($vm, $at);
                $vm->refresh();
                $fields = $quote['kind'] === 'pricing'
                    ? ['pricing_mode', 'custom_monthly_price', 'custom_stopped_monthly_price']
                    : ['cpu_cores', 'ram_gb', 'disk_gb', 'status'];
                $vm->fill(array_intersect_key($quote['after'], array_flip($fields)));
                if ($quote['kind'] === 'pricing') {
                    $vm->pricing_version++;
                } else {
                    $vm->hardware_drift = false;
                    $vm->remote_state = array_merge($vm->remote_state ?? [], ['accepted_hardware' => $remote]);
                }
                $vm->desired_state = array_merge($vm->desired_state ?? [], $vm->desiredStateSnapshot());
                $vm->save();
            }
            VmConfigurationChange::create(['virtual_machine_id' => $vm->id, 'actor_id' => $actor->id,
                'request_key' => $quote['request_key'], 'kind' => $quote['kind'] === 'hardware' ? 'hardware_requested' : $quote['kind'],
                'reason' => $quote['reason'], 'before' => $quote['before'], 'after' => $quote['after'], 'effective_at' => now()]);
            app(PortalCache::class)->invalidate();

            return $order;
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['configuration' => $message]);
    }
}
