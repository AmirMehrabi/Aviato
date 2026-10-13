<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_integration_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20)->default('sync');
            $table->string('status', 20)->default('queued')->index();
            $table->string('batch_id', 64)->nullable()->unique();
            $table->json('payload')->nullable();
            $table->json('stats')->nullable();
            $table->json('health')->nullable();
            $table->json('errors')->nullable();
            $table->unsignedInteger('rejected_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_integration_runs');
    }
};
