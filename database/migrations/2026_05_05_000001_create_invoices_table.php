<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('issued');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('issued_at')->nullable();
            $table->string('currency', 10)->default('IRR');
            $table->bigInteger('subtotal_amount')->default(0);
            $table->bigInteger('wallet_charged_amount')->default(0);
            $table->bigInteger('adjustment_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'period_start', 'period_end']);
            $table->index(['customer_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
