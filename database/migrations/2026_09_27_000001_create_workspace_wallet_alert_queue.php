<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_wallet_alert_queue', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_deferred')->default(false);
            $table->unsignedTinyInteger('threshold_percent')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('sms_status', 20)->default('pending');
            $table->unsignedSmallInteger('sms_attempts')->default(0);
            $table->timestamp('last_sms_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->index(['status', 'id']);
            $table->index(['customer_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_wallet_alert_queue');
    }
};
