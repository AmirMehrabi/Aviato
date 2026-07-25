<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('auto_suspend_vms')->default(true)->after('sms_notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('auto_suspend_vms');
        });
    }
};
