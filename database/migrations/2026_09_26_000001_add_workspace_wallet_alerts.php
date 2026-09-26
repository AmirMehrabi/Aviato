<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('wallet_alert_thresholds')->nullable();
            $table->json('wallet_alert_recipient_ids')->nullable();
        });

        Schema::create('workspace_wallet_alert_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('threshold_percent');
            $table->timestamp('notified_at');
            $table->unique(['project_id', 'customer_id', 'threshold_percent'], 'workspace_wallet_alert_state_unique');
        });

        Schema::create('workspace_wallet_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedTinyInteger('threshold_percent')->nullable();
            $table->bigInteger('effective_balance');
            $table->decimal('remaining_percent', 10, 2)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_wallet_alert_deliveries');
        Schema::dropIfExists('workspace_wallet_alert_states');
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['wallet_alert_thresholds', 'wallet_alert_recipient_ids']);
        });
    }
};
