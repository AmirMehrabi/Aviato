<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            // Existing servers keep their recoverable credentials.
            $table->boolean('retain_login_password')->default(true);
            $table->text('login_password_hash')->nullable();
            $table->string('password_reset_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropColumn(['retain_login_password', 'login_password_hash', 'password_reset_status']);
        });
    }
};
