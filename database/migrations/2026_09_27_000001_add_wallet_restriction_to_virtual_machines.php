<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->json('wallet_restriction')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropColumn('wallet_restriction');
        });
    }
};
