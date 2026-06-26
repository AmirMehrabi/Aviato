<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reseller_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('usage_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->unsignedBigInteger('settlement_amount')->default(0);
            $table->decimal('commission_pct', 5, 2);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->string('payout_method', 20);
            $table->string('status', 20)->default('pending');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('withdrawal_request_id')->nullable()->constrained('reseller_withdrawal_requests')->nullOnDelete();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['reseller_id', 'service_date']);
            $table->index(['customer_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_commissions');
    }
};
