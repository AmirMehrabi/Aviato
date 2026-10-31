<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->boolean('dns_read')->default(false);
            $table->boolean('dns_manage')->default(false);
        });
        Schema::create('dns_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 253)->unique();
            $table->string('plan')->default('free');
            $table->string('verification_token', 80);
            $table->timestamp('verification_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('unverified');
            $table->string('delegation_status')->default('pending');
            $table->json('observed_nameservers')->nullable();
            $table->json('node_status')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->unsignedBigInteger('synced_revision')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('delete_requested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('dns_record_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name', 254);
            $table->string('type', 10);
            $table->unsignedInteger('ttl')->default(300);
            $table->json('records');
            $table->timestamps();
            $table->unique(['dns_zone_id', 'name', 'type']);
        });
        Schema::create('dns_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('revision');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
        Schema::create('dns_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('plan');
            $table->unsignedInteger('zones');
            $table->unsignedInteger('records');
            $table->timestamps();
            $table->unique(['project_id', 'date', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_usage_snapshots');
        Schema::dropIfExists('dns_changes');
        Schema::dropIfExists('dns_record_sets');
        Schema::dropIfExists('dns_zones');
        Schema::table('project_members', fn (Blueprint $table) => $table->dropColumn(['dns_read', 'dns_manage']));
    }
};
