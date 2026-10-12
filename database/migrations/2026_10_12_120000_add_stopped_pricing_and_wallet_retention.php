<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_bundles', function (Blueprint $table): void {
            $table->unsignedBigInteger('stopped_monthly_price')->nullable();
        });
        Schema::table('vm_bundle_location_mappings', function (Blueprint $table): void {
            $table->unsignedBigInteger('stopped_monthly_price')->nullable();
        });
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->timestamp('wallet_depleted_at')->nullable();
            $table->timestamp('wallet_retention_expires_at')->nullable()->index();
            $table->json('wallet_retention_notices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropIndex(['wallet_retention_expires_at']);
            $table->dropColumn(['wallet_depleted_at', 'wallet_retention_expires_at', 'wallet_retention_notices']);
        });
        Schema::table('vm_bundle_location_mappings', fn (Blueprint $table) => $table->dropColumn('stopped_monthly_price'));
        Schema::table('vm_bundles', fn (Blueprint $table) => $table->dropColumn('stopped_monthly_price'));
    }
};
