<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('password')->index();
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['status', 'suspended_at', 'suspension_reason']);
        });
    }
};
