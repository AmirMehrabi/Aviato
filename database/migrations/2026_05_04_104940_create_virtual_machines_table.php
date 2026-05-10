<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vm_bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('vmid')->nullable();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('node')->nullable();
            $table->string('storage')->nullable();
            $table->string('os_template')->nullable();
            $table->string('iso_volume')->nullable();
            $table->string('network_bridge')->nullable();
            $table->string('ip_address')->nullable();
            $table->unsignedSmallInteger('cpu_cores');
            $table->unsignedInteger('ram_gb');
            $table->unsignedInteger('disk_gb');
            $table->unsignedSmallInteger('ip_count')->default(1);
            $table->string('status')->default('stopped');
            $table->string('provisioning_status')->default('pending');
            $table->json('desired_state')->nullable();
            $table->json('remote_state')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_stopped_at')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->unsignedBigInteger('unbilled_amount')->default(0);
            $table->timestamps();

            $table->unique(['proxmox_server_id', 'vmid']);
            $table->index(['customer_id', 'status']);
            $table->index(['provisioning_status', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_machines');
    }
};
