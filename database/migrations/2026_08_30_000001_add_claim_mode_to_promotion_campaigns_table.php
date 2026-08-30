<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table): void {
            $table->string('claim_mode', 30)->default('payment_required')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table): void {
            $table->dropColumn('claim_mode');
        });
    }
};
