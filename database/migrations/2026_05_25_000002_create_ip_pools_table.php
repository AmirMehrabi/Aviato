<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxmox_server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('node')->nullable();
            $table->string('network_bridge')->default('vmbr1');
            $table->string('gateway');
            $table->unsignedTinyInteger('prefix_length')->default(24);
            $table->string('nameservers')->nullable();
            $table->string('start_ip');
            $table->string('end_ip')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['proxmox_server_id', 'is_active']);
        });

        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->unique();
            $table->string('status')->default('available');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['ip_pool_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('ip_pools');
    }
};
