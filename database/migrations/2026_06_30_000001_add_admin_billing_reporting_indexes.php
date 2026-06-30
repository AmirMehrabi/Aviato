<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_index');
            $table->index(['provider', 'created_at'], 'payments_provider_created_at_index');
        });
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->index(['created_by_id', 'created_at'], 'wallet_transactions_actor_created_at_index');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['status', 'issued_at'], 'invoices_status_issued_at_index');
        });
        Schema::table('usage_settlements', function (Blueprint $table): void {
            $table->index(['customer_id', 'service_date'], 'usage_settlements_customer_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_paid_at_index');
            $table->dropIndex('payments_provider_created_at_index');
        });
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropIndex('wallet_transactions_actor_created_at_index'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('invoices_status_issued_at_index'));
        Schema::table('usage_settlements', fn (Blueprint $table) => $table->dropIndex('usage_settlements_customer_date_index'));
    }
};
