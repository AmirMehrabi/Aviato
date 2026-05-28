<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_disks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vm_upgrade_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk_device');
            $table->string('storage')->nullable();
            $table->unsignedInteger('size_gb');
            $table->string('status')->default('pending');
            $table->timestamp('last_billed_at')->nullable();
            $table->json('remote_state')->nullable();
            $table->timestamps();

            $table->unique(['virtual_machine_id', 'disk_device']);
            $table->index(['status', 'last_billed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_disks');
    }
};
