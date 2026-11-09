<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\VirtualMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class VmConsoleService
{
    public function __construct(private readonly ProxmoxService $proxmox, private readonly VmActivityRecorder $activities) {}

    public function session(Request $request, VirtualMachine $vm, string $portal): JsonResponse
    {
        $reference = (string) Str::uuid();
        $actor = $request->user($portal === 'admin' ? 'admin' : 'customer');
        $metadata = ['reference' => $reference, 'portal' => $portal, 'actor_id' => $actor?->id, 'actor_name' => $actor?->name];
        $code = match (true) {
            ! $vm->isProxmox(), $vm->isLxc() => 'unsupported',
            ! $vm->proxmoxServer || blank($vm->node) || blank($vm->vmid) => 'not_configured',
            $vm->isActionLocked() => 'locked',
            $vm->provisioning_status !== VirtualMachine::PROVISION_READY => 'provisioning',
            default => null,
        };

        try {
            if ($code === null) {
                $console = $this->proxmox->qemuConsoleSession($vm->proxmoxServer, (string) $vm->node, (int) $vm->vmid);
                $ttl = max(15, (int) config('console.session_ttl', 60));
                $path = rtrim((string) config('console.proxy_path', '/console-ws'), '/')
                    .'/'.$vm->proxmox_server_id.'/nodes/'.rawurlencode((string) $vm->node).'/qemu/'.(int) $vm->vmid.'/vncwebsocket';
                $this->activities->record($vm, 'console.session', 'success', 'نشست کنسول صادر شد', actor: $actor instanceof Customer ? $actor : null, metadata: $metadata);

                return response()->json([
                    'websocket_url' => $path.'?'.http_build_query(['port' => (int) $console['port'], 'vncticket' => (string) $console['ticket']]),
                    'password' => (string) $console['ticket'],
                    'expires_in' => $ttl,
                    'expires_at' => now()->addSeconds($ttl)->toISOString(),
                    'reference' => $reference,
                ])->header('Cache-Control', 'no-store, private');
            }
        } catch (Throwable $exception) {
            report($exception);
            $code = 'upstream';
        }

        $this->activities->record($vm, 'console.session', 'failed', 'ایجاد نشست کنسول ناموفق بود', actor: $actor instanceof Customer ? $actor : null, metadata: [...$metadata, 'code' => $code]);

        return response()->json([
            'message' => 'نشست کنسول ایجاد نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',
            'code' => $code,
            'retryable' => $code === 'upstream',
            'reference' => $reference,
        ], 422)->header('Cache-Control', 'no-store, private');
    }

    public function powerActions(VirtualMachine $vm, string $portal, bool $allowed): array
    {
        if (! $allowed || $vm->isLxc() || $vm->isActionLocked() || $vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            return [];
        }

        $keys = $vm->isRunning() ? ($portal === 'admin' ? ['stop', 'force-stop'] : ['stop', 'restart', 'force-stop']) : ['start'];
        $labels = ['start' => 'روشن کردن', 'stop' => 'خاموش کردن', 'restart' => 'راه‌اندازی مجدد', 'force-stop' => 'خاموش کردن اجباری'];

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => $labels[$key],
            'url' => route(($portal === 'admin' ? 'admin.virtual-machines.' : 'customer.servers.').$key, $vm, false),
            'warning' => $key === 'force-stop'
                ? 'برق ماشین بدون انتظار برای سیستم‌عامل قطع می‌شود. اطلاعات ذخیره‌نشده ممکن است از بین برود. فقط زمانی استفاده کنید که خاموش کردن عادی پاسخ نمی‌دهد.'
                : ($key === 'start' ? 'ماشین روشن می‌شود و هزینه مصرف مطابق تعرفه محاسبه خواهد شد.' : 'این عملیات اتصال کنسول و سرویس‌های در حال اجرای ماشین را مختل می‌کند. ادامه می‌دهید؟'),
        ], $keys);
    }
}
