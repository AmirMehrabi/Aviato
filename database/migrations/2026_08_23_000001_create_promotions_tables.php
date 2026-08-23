<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_promotions')->default(false)->after('password');
        });

        Schema::create('promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('type', 30);
            $table->string('audience', 30)->default('all');
            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 10)->default('IRR');
            $table->bigInteger('credit_amount')->nullable();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->bigInteger('minimum_top_up')->nullable();
            $table->bigInteger('maximum_bonus')->nullable();
            $table->unsignedInteger('code_count');
            $table->bigInteger('maximum_liability');
            $table->string('headline')->nullable();
            $table->text('message')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('rules_locked_at')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotion_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('code_digest', 64)->unique();
            $table->text('encrypted_code');
            $table->string('status', 20)->default('available')->index();
            $table->unsignedBigInteger('reserved_payment_id')->nullable()->index();
            $table->foreignId('reserved_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['promotion_campaign_id', 'status']);
        });

        Schema::create('promotion_campaign_customer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['promotion_campaign_id', 'customer_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('promotion_code_id')->nullable()->after('wallet_id')->constrained('promotion_codes')->nullOnDelete();
            $table->foreignId('promotion_redeemer_customer_id')->nullable()->after('promotion_code_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('promotion_project_id')->nullable()->after('promotion_redeemer_customer_id')->constrained('projects')->nullOnDelete();
            $table->bigInteger('promotion_bonus_amount')->default(0)->after('amount');
        });

        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('promotion_code_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('redeemed_by_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->bigInteger('base_amount')->default(0);
            $table->bigInteger('benefit_amount');
            $table->timestamp('redeemed_at');
            $table->timestamps();
            $table->unique(['promotion_campaign_id', 'wallet_id']);
        });

        Schema::create('promotion_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['promotion_campaign_id', 'created_at']);
        });

        Schema::create('promotion_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('promotion_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->bigInteger('expected_bonus');
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_exceptions');
        Schema::dropIfExists('promotion_events');
        Schema::dropIfExists('promotion_redemptions');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_code_id');
            $table->dropConstrainedForeignId('promotion_redeemer_customer_id');
            $table->dropConstrainedForeignId('promotion_project_id');
            $table->dropColumn('promotion_bonus_amount');
        });
        Schema::dropIfExists('promotion_campaign_customer');
        Schema::dropIfExists('promotion_codes');
        Schema::dropIfExists('promotion_campaigns');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_manage_promotions'));
    }
};
