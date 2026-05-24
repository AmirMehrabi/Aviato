<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('vm_usage');
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->string('unit', 30)->default('hour');
            $table->decimal('unit_price', 14, 6)->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
