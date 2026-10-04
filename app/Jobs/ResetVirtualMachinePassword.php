<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\VmActivityRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class ResetVirtualMachinePassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public int $timeout = 600;

    public function __construct(
        public readonly int $virtualMachineId,
        public readonly string $resetId,
        public readonly string $passwordHash,
        public readonly ?string $legacyPassword = null,
    ) {}

    public function handle(ProxmoxService $proxmox, VmActivityRecorder $activities): void
    {
        $vm = VirtualMachine::with(['cloudImage', 'proxmoxServer'])->findOrFail($this->virtualMachineId);
        if ($vm->password_reset_status !== 'pending' || data_get($vm->remote_state, 'password_reset_id') !== $this->resetId) {
            return;
        }

        try {
            if (! $vm->supportsCloudInitPasswordReset() || ! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid
                || $vm->provisioning_status !== VirtualMachine::PROVISION_READY || $vm->isDeleted() || $vm->isDeleting()) {
                throw new RuntimeException('VM is no longer eligible for CloudInit password reset.');
            }

            $config = $proxmox->vmConfig($vm->proxmoxServer, $vm->node, $vm->vmid);
            $hasCloudInitDrive = collect($config)->contains(fn ($value, $key): bool => preg_match('/^(ide|sata|scsi)\d+$/', (string) $key)
                && is_string($value) && str_contains($value, 'cloudinit'));
            // Custom user-data ignores cipassword; custom metadata can keep the
            // instance-id fixed and prevent the password module from running.
            if (! $hasCloudInitDrive || preg_match('/(?:^|,)(?:user|meta)=/', (string) ($config['cicustom'] ?? ''))
                || ! in_array($config['citype'] ?? 'nocloud', ['nocloud', 'configdrive2'], true)) {
                throw new RuntimeException('CloudInit drive or metadata does not support password reset.');
            }

            $status = $proxmox->vmStatus($vm->proxmoxServer, $vm->node, $vm->vmid);
            if (($status['status'] ?? null) !== 'running') {
                throw new RuntimeException('VM must be running before password reset.');
            }

            $proxmox->setCloudInitPassword($vm->proxmoxServer, $vm->node, $vm->vmid, $this->passwordHash);
            $vm->update([
                'login_password' => $vm->retain_login_password && $this->legacyPassword !== null ? Crypt::decryptString($this->legacyPassword) : null,
                'login_password_hash' => $vm->retain_login_password ? null : $this->passwordHash,
            ]);
            $result = $proxmox->shutdownVm($vm->proxmoxServer, $vm->node, $vm->vmid, false, ['source' => 'password_reset'], false);
            $this->wait($proxmox, $vm, $result);
            if (($proxmox->vmStatus($vm->proxmoxServer, $vm->node, $vm->vmid)['status'] ?? null) !== 'stopped') {
                throw new RuntimeException('VM did not shut down; password reset was not completed.');
            }
            $vm->update(['status' => VirtualMachine::STATUS_STOPPED, 'last_stopped_at' => now()]);

            // Rebuild the drive while stopped so the next boot reads the new seed.
            $this->wait($proxmox, $vm, $proxmox->regenerateCloudInit($vm->proxmoxServer, $vm->node, $vm->vmid));
            $this->wait($proxmox, $vm, $proxmox->startVm($vm->proxmoxServer, $vm->node, $vm->vmid));

            $vm->refresh()->forceFill([
                'password_reset_status' => 'succeeded',
                'status' => VirtualMachine::STATUS_RUNNING,
                'last_started_at' => now(),
                'desired_state' => array_merge($vm->desired_state ?? [], [
                    'status' => VirtualMachine::STATUS_RUNNING,
                    'power_generation' => (int) data_get($vm->desired_state, 'power_generation', 0) + 1,
                ]),
            ])->save();
            $activities->record($vm, 'password_reset', 'succeeded', 'رمز جدید به CloudInit ارسال و سرور راه‌اندازی مجدد شد');
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    private function wait(ProxmoxService $proxmox, VirtualMachine $vm, array $result): void
    {
        if (! empty($result['task_id'])) {
            $proxmox->waitForTask($vm->proxmoxServer, $vm->node, (string) $result['task_id'], 180);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $vm = VirtualMachine::find($this->virtualMachineId);
        if ($vm && $vm->password_reset_status === 'pending' && data_get($vm->remote_state, 'password_reset_id') === $this->resetId) {
            $vm->update(['password_reset_status' => 'failed']);
            app(VmActivityRecorder::class)->record($vm, 'password_reset', 'failed', 'بازنشانی رمز یا راه‌اندازی مجدد کامل نشد؛ وضعیت سرور را بررسی کنید');
        }
    }
}
