<?php

namespace App\Services;

use App\Jobs\ApplyVmUpgradeJob;
use App\Models\Customer;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VmUpgradeService
{
    private const WALLET_COVERAGE_HOURS = 24;

    public function __construct(
        private readonly BillingService $billing,
        private readonly UsageBillingService $usageBilling,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewBundleUpgrade(VirtualMachine $vm, VmBundle $bundle): array
    {
        $vm->loadMissing('bundle');
        $beforeHourly = $this->billing->hourlyWhenRunning($vm);
        $afterHourly = (float) $bundle->hourly_price;

        return [
            'type' => VmUpgradeOrder::TYPE_BUNDLE,
            'before_monthly' => $this->billing->estimateMonthly($vm),
            'after_monthly' => (int) round($afterHourly * ResourceRate::hoursPerMonth()),
            'monthly_delta' => max(0, (int) round(($afterHourly - $beforeHourly) * ResourceRate::hoursPerMonth())),
            'minimum_wallet_balance' => (int) ceil($afterHourly * self::WALLET_COVERAGE_HOURS),
            'requires_reboot' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewExtraDisk(VirtualMachine $vm, int $sizeGb): array
    {
        $hourly = $this->billing->diskHourly($sizeGb);

        return [
            'type' => VmUpgradeOrder::TYPE_EXTRA_DISK,
            'size_gb' => $sizeGb,
            'monthly_delta' => (int) round($hourly * ResourceRate::hoursPerMonth()),
            'minimum_wallet_balance' => (int) ceil($hourly * self::WALLET_COVERAGE_HOURS),
            'requires_guest_mount' => true,
        ];
    }

    public function requestBundleUpgrade(Customer $customer, VirtualMachine $vm, VmBundle $bundle): VmUpgradeOrder
    {
        $order = DB::transaction(function () use ($customer, $vm, $bundle): VmUpgradeOrder {
            $locked = $this->lockedCustomerVm($customer, $vm->id);
            $locked->loadMissing('bundle');

            $this->assertVmCanUpgrade($locked);
            $this->assertBundleUpgrade($locked, $bundle);
            $this->usageBilling->chargeVm($locked);

            $preview = $this->previewBundleUpgrade($locked->refresh(), $bundle);
            $this->assertWalletCanStart($customer, $preview['minimum_wallet_balance']);

            return VmUpgradeOrder::create([
                'customer_id' => $customer->id,
                'virtual_machine_id' => $locked->id,
                'from_bundle_id' => $locked->vm_bundle_id,
                'to_bundle_id' => $bundle->id,
                'type' => VmUpgradeOrder::TYPE_BUNDLE,
                'status' => VmUpgradeOrder::STATUS_PENDING,
                'before_snapshot' => $locked->desiredStateSnapshot(),
                'after_snapshot' => [
                    'vm_bundle_id' => $bundle->id,
                    'bundle_name' => $bundle->name,
                    'cpu_cores' => $bundle->cpu_cores,
                    'ram_gb' => $bundle->ram_gb,
                    'disk_gb' => max($locked->disk_gb, $bundle->disk_gb),
                    'ip_count' => $bundle->ip_count,
                ],
                'minimum_wallet_balance' => $preview['minimum_wallet_balance'],
                'estimated_monthly_delta' => $preview['monthly_delta'],
            ]);
        });

        ApplyVmUpgradeJob::dispatch($order->id)->onQueue(ApplyVmUpgradeJob::QUEUE);

        return $order;
    }

    public function requestExtraDisk(Customer $customer, VirtualMachine $vm, int $sizeGb): VmUpgradeOrder
    {
        $order = DB::transaction(function () use ($customer, $vm, $sizeGb): VmUpgradeOrder {
            $locked = $this->lockedCustomerVm($customer, $vm->id);
            $this->assertVmCanUpgrade($locked);
            $this->assertExtraDiskSize($sizeGb);
            $this->usageBilling->chargeVm($locked);

            $preview = $this->previewExtraDisk($locked->refresh(), $sizeGb);
            $this->assertWalletCanStart($customer, $preview['minimum_wallet_balance']);

            $order = VmUpgradeOrder::create([
                'customer_id' => $customer->id,
                'virtual_machine_id' => $locked->id,
                'type' => VmUpgradeOrder::TYPE_EXTRA_DISK,
                'status' => VmUpgradeOrder::STATUS_PENDING,
                'before_snapshot' => $locked->desiredStateSnapshot(),
                'after_snapshot' => [
                    'size_gb' => $sizeGb,
                    'storage' => $locked->storage,
                ],
                'minimum_wallet_balance' => $preview['minimum_wallet_balance'],
                'estimated_monthly_delta' => $preview['monthly_delta'],
            ]);

            VmDisk::create([
                'virtual_machine_id' => $locked->id,
                'vm_upgrade_order_id' => $order->id,
                'disk_device' => 'pending-'.$order->id,
                'storage' => $locked->storage,
                'size_gb' => $sizeGb,
                'status' => VmDisk::STATUS_PENDING,
            ]);

            return $order;
        });

        ApplyVmUpgradeJob::dispatch($order->id)->onQueue(ApplyVmUpgradeJob::QUEUE);

        return $order;
    }

    private function lockedCustomerVm(Customer $customer, int $vmId): VirtualMachine
    {
        return VirtualMachine::query()
            ->whereKey($vmId)
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertVmCanUpgrade(VirtualMachine $vm): void
    {
        if ($vm->isActionLocked()) {
            throw ValidationException::withMessages(['server' => 'این سرور در وضعیت حذف است و قابل ارتقا نیست.']);
        }

        if ($vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            throw ValidationException::withMessages(['server' => 'ارتقا فقط برای سرور آماده امکان پذیر است.']);
        }

        if (! $vm->proxmox_server_id || ! $vm->node || ! $vm->vmid) {
            throw ValidationException::withMessages(['server' => 'اتصال Proxmox این سرور کامل نیست.']);
        }

        $hasPending = $vm->pendingUpgradeOrders()->exists();
        if ($hasPending) {
            throw ValidationException::withMessages(['server' => 'یک ارتقای دیگر برای این سرور در حال انجام است.']);
        }
    }

    private function assertBundleUpgrade(VirtualMachine $vm, VmBundle $bundle): void
    {
        if (! $bundle->is_active) {
            throw ValidationException::withMessages(['bundle' => 'این باندل فعال نیست.']);
        }

        if ($bundle->cpu_cores < $vm->cpu_cores || $bundle->ram_gb < $vm->ram_gb || $bundle->disk_gb < $vm->disk_gb) {
            throw ValidationException::withMessages(['bundle' => 'در این نسخه فقط ارتقا به منابع بزرگتر مجاز است.']);
        }

        if ($bundle->id === $vm->vm_bundle_id) {
            throw ValidationException::withMessages(['bundle' => 'این باندل همین حالا روی سرور فعال است.']);
        }
    }

    private function assertExtraDiskSize(int $sizeGb): void
    {
        if (! in_array($sizeGb, [10, 25, 50, 100, 250, 500], true)) {
            throw ValidationException::withMessages(['size_gb' => 'اندازه دیسک انتخاب شده معتبر نیست.']);
        }
    }

    private function assertWalletCanStart(Customer $customer, int $minimumBalance): void
    {
        $wallet = $this->wallets->walletFor($customer->refresh());

        if ($wallet->balance < $minimumBalance) {
            throw ValidationException::withMessages([
                'wallet' => 'موجودی کیف پول برای شروع این ارتقا کافی نیست.',
            ]);
        }
    }
}
