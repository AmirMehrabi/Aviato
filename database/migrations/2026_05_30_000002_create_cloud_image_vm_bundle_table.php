<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_image_vm_bundle', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cloud_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vm_bundle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cloud_image_id', 'vm_bundle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_image_vm_bundle');
    }
};
