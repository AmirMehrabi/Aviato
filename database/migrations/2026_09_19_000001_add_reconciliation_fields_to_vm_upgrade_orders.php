<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_upgrade_orders', function (Blueprint $table): void {
            $table->json('progress')->nullable()->after('proxmox_task_id');
            $table->timestamp('last_attempt_at')->nullable()->after('progress');
            $table->timestamp('reconcile_after')->nullable()->after('last_attempt_at');
            $table->index(['status', 'reconcile_after'], 'vm_upgrade_orders_reconcile_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vm_upgrade_orders', function (Blueprint $table): void {
            $table->dropIndex('vm_upgrade_orders_reconcile_idx');
            $table->dropColumn(['progress', 'last_attempt_at', 'reconcile_after']);
        });
    }
};
