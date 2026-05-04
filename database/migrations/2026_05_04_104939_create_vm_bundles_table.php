<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cpu_cores');
            $table->unsignedInteger('ram_gb');
            $table->unsignedInteger('disk_gb');
            $table->unsignedSmallInteger('ip_count')->default(1);
            $table->unsignedBigInteger('monthly_price')->default(0);
            $table->decimal('hourly_price', 14, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_bundles');
    }
};
