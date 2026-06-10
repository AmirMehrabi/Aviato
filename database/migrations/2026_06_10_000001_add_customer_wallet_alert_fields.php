<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('sms_notifications_enabled')->default(true)->after('suspension_reason');
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_notification_count')->default(0)->after('last_transaction_at');
            $table->timestamp('negative_notified_at')->nullable()->after('negative_notification_count');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn(['negative_notification_count', 'negative_notified_at']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('sms_notifications_enabled');
        });
    }
};
