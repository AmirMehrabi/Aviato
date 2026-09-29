<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('hesabro_status')->nullable()->index();
            $table->uuid('hesabro_idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('hesabro_factor_id')->nullable();
            $table->unsignedInteger('hesabro_attempts')->default(0);
            $table->string('hesabro_error', 500)->nullable();
            $table->timestamp('hesabro_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['hesabro_status', 'hesabro_idempotency_key', 'hesabro_factor_id', 'hesabro_attempts', 'hesabro_error', 'hesabro_submitted_at']);
        });
    }
};
