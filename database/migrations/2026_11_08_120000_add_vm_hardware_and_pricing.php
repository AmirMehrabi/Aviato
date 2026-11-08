<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->string('pricing_mode')->default('inherit');
            $table->unsignedBigInteger('custom_monthly_price')->nullable();
            $table->unsignedBigInteger('custom_stopped_monthly_price')->nullable();
            $table->unsignedInteger('pricing_version')->default(0);
            $table->json('hardware_observation')->nullable();
            $table->boolean('hardware_drift')->default(false)->index();
            $table->timestamp('hardware_checked_at')->nullable();
            $table->timestamp('hardware_attempted_at')->nullable()->index();
            $table->text('hardware_sync_error')->nullable();
        });
        Schema::create('vm_configuration_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('kind');
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->timestamp('effective_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_configuration_changes');
        Schema::table('virtual_machines', fn (Blueprint $table) => $table->dropColumn([
            'pricing_mode', 'custom_monthly_price', 'custom_stopped_monthly_price', 'pricing_version',
            'hardware_observation', 'hardware_drift', 'hardware_checked_at', 'hardware_attempted_at', 'hardware_sync_error',
        ]));
    }
};
