<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_products', function (Blueprint $table): void {
            $table->string('provider', 40)->default('reseller_world')->index();
            $table->timestamp('price_valid_until')->nullable();
            $table->dropUnique(['extension', 'operation', 'period']);
            $table->unique(['provider', 'extension', 'operation', 'period'], 'domain_products_provider_offer_unique');
        });
        foreach (['domain_orders', 'managed_domains'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('provider', 40)->default('reseller_world')->index();
            });
        }
    }

    public function down(): void
    {
        // Provider offers must be archived explicitly before removing their identities.
        if (DB::table('domain_products')->where('provider', '!=', 'reseller_world')->exists()
            || DB::table('domain_orders')->where('provider', '!=', 'reseller_world')->exists()
            || DB::table('managed_domains')->where('provider', '!=', 'reseller_world')->exists()) {
            throw new RuntimeException('Remove additional provider offers before rolling back provider routing.');
        }
        foreach (['domain_orders', 'managed_domains'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('provider'));
        }
        Schema::table('domain_products', function (Blueprint $table): void {
            $table->dropUnique('domain_products_provider_offer_unique');
            $table->unique(['extension', 'operation', 'period']);
            $table->dropColumn(['provider', 'price_valid_until']);
        });
    }
};
