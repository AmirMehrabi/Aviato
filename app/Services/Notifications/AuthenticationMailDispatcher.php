<?php

namespace App\Services\Notifications;

use App\Jobs\SendAuthenticationEmail;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthenticationMailDispatcher
{
    public function send(Customer $customer, string $event, string $code): void
    {
        $connection = EmailQueue::connection();
        $id = DB::table('notification_deliveries')->insertGetId([
            'event' => $event, 'recipient' => $customer->email, 'status' => 'queued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            SendAuthenticationEmail::dispatch($id, $customer->id, $event, $code, now()->addMinutes(10)->timestamp)
                ->onConnection($connection)->onQueue('auth-mail')->afterCommit();
        } catch (Throwable $exception) {
            DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'failed', 'updated_at' => now()]);
            throw $exception;
        }
    }
}
