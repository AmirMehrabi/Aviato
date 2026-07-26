<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 32)->index();
            $table->string('affected_service');
            $table->text('impact_summary');
            $table->longText('summary');
            $table->longText('root_cause')->nullable();
            $table->longText('customer_impact')->nullable();
            $table->longText('resolution')->nullable();
            $table->longText('next_steps')->nullable();
            $table->string('final_status')->nullable();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->dateTime('published_at')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();
        });

        Schema::create('incident_timeline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->string('title');
            $table->text('description');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['incident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_timeline_events');
        Schema::dropIfExists('incidents');
    }
};
