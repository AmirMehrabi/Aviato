<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_upgrade_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_bundle_id')->nullable()->constrained('vm_bundles')->nullOnDelete();
            $table->foreignId('to_bundle_id')->nullable()->constrained('vm_bundles')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->unsignedBigInteger('minimum_wallet_balance')->default(0);
            $table->unsignedBigInteger('estimated_monthly_delta')->default(0);
            $table->string('proxmox_task_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['virtual_machine_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_upgrade_orders');
    }
};
