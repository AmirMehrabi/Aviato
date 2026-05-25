<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxmox_server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('node');
            $table->unsignedBigInteger('template_vmid');
            $table->string('default_username')->default('ubuntu');
            $table->string('storage')->nullable();
            $table->string('disk_device')->default('scsi0');
            $table->string('network_bridge')->default('vmbr0');
            $table->string('ostype')->default('l26');
            $table->unsignedSmallInteger('min_cpu_cores')->default(1);
            $table->unsignedInteger('min_ram_gb')->default(1);
            $table->unsignedInteger('min_disk_gb')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['proxmox_server_id', 'template_vmid']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_images');
    }
};
