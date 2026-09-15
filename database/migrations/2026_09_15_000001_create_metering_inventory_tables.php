<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metering_inventory_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('assignment_id')->unique();
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('vm_uuid')->index();
            $table->ipAddress('ip_address')->index();
            $table->string('mac_address', 17)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_server_id', 120)->nullable();
            $table->string('provider_vm_id', 120)->nullable();
            $table->string('node', 120)->nullable();
            $table->string('display_name', 255);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->char('payload_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['virtual_machine_id', 'active']);
        });

        Schema::create('metering_inventory_changes', function (Blueprint $table): void {
            $table->bigIncrements('stream_id');
            $table->ulid('assignment_id')->index();
            $table->string('action', 16);
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metering_inventory_changes');
        Schema::dropIfExists('metering_inventory_assignments');
    }
};
