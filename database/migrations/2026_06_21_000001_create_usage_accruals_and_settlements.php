<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key');
            $table->date('service_date');
            $table->unsignedBigInteger('amount')->default(0);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'scope_key', 'service_date'], 'usage_settlements_customer_scope_date_unique');
            $table->index(['service_date', 'settled_at']);
        });

        Schema::create('usage_accruals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key');
            $table->string('category', 40);
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('virtual_machine_id')->nullable();
            $table->string('resource_name')->nullable();
            $table->date('service_date');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('accrued_seconds')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->json('segments')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('usage_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_id', 'scope_key', 'category', 'resource_type', 'resource_id', 'service_date'],
                'usage_accruals_resource_date_unique',
            );
            $table->index(['customer_id', 'service_date', 'settled_at']);
            $table->index(['project_id', 'service_date', 'settled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_accruals');
        Schema::dropIfExists('usage_settlements');
    }
};
