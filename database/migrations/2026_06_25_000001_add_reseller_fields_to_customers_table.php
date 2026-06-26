<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('is_reseller')->default(false);
            $table->decimal('reseller_commission_pct', 5, 2)->nullable();
            $table->string('reseller_payout_method', 20)->nullable()->comment('auto_credit or withdrawable');
            $table->bigInteger('reseller_earnings_balance')->default(0);
            $table->string('reseller_code', 32)->nullable()->unique();
            $table->string('reseller_status', 20)->nullable()->comment('active or suspended');
            $table->timestamp('reseller_activated_at')->nullable();

            $table->index('is_reseller');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['is_reseller']);
            $table->dropColumn([
                'is_reseller',
                'reseller_commission_pct',
                'reseller_payout_method',
                'reseller_earnings_balance',
                'reseller_code',
                'reseller_status',
                'reseller_activated_at',
            ]);
        });
    }
};
