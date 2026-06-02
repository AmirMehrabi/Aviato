<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->text('national_code')->nullable()->after('phone');
            $table->string('national_code_hash', 64)->nullable()->after('national_code');
            $table->timestamp('national_code_verified_at')->nullable()->after('national_code_hash');

            $table->unique('national_code_hash');
            $table->index('national_code_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['national_code_verified_at']);
            $table->dropUnique(['national_code_hash']);
            $table->dropColumn(['national_code', 'national_code_hash', 'national_code_verified_at']);
        });
    }
};
