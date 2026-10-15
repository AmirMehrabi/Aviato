<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_billing_policy_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vm_network_billing_period_id')->constrained(indexName: 'net_policy_adjustment_period_fk')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('policy_before');
            $table->json('policy_after');
            $table->unsignedBigInteger('amount_before');
            $table->unsignedBigInteger('amount_after');
            $table->bigInteger('amount_delta');
            $table->foreignId('usage_accrual_id')->nullable()->constrained()->nullOnDelete();
            $table->json('accrual_changes')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_billing_policy_adjustments');
    }
};
