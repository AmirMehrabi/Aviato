<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_rates', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->unique();
            $table->string('label');
            $table->string('unit');
            $table->decimal('hourly_price', 14, 6)->default(0);
            $table->unsignedBigInteger('monthly_price')->default(0);
            $table->string('billing_policy')->default('running');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_rates');
    }
};
