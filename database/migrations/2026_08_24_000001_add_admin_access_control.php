<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default('admin')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        DB::table('users')->update(['role' => 'admin', 'is_active' => true]);

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('event', 120)->index();
            $table->string('method', 12)->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('path', 1000)->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('result', 30)->index();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('admin_session_users', function (Blueprint $table): void {
            $table->string('session_id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_seen_at')->useCurrent()->index();
            $table->index(['user_id', 'last_seen_at']);
        });

        if (Schema::hasColumn('users', 'can_manage_promotions')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_manage_promotions'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_session_users');
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active', 'last_login_at', 'last_login_ip']);
            $table->boolean('can_manage_promotions')->default(false);
        });
    }
};
