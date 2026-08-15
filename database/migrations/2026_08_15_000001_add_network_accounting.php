<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_bundles', function (Blueprint $table): void {
            $table->boolean('network_accounting_enabled')->default(false);
            $table->unsignedBigInteger('network_included_bytes_monthly')->default(1099511627776);
            $table->unsignedBigInteger('network_overage_price')->default(9000);
            $table->unsignedBigInteger('network_overage_price_unit_bytes')->default(1073741824);
            $table->string('network_usage_direction', 16)->default('both');
            $table->string('network_billing_timezone', 64)->default('Asia/Tehran');
        });

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->boolean('network_accounting_enabled_override')->nullable();
            $table->unsignedBigInteger('network_included_bytes_monthly_override')->nullable();
            $table->unsignedBigInteger('network_overage_price_override')->nullable();
            $table->unsignedBigInteger('network_overage_price_unit_bytes_override')->nullable();
            $table->string('network_usage_direction_override', 16)->nullable();
            $table->string('network_billing_timezone_override', 64)->nullable();
        });

        Schema::create('vm_network_billing_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('timezone', 64);
            $table->string('direction', 16);
            $table->unsignedBigInteger('included_bytes');
            $table->unsignedBigInteger('price_per_unit');
            $table->unsignedBigInteger('price_unit_bytes');
            $table->string('currency', 10)->default('IRR');
            $table->json('policy_snapshot');
            $table->unsignedBigInteger('ingress_bytes')->default(0);
            $table->unsignedBigInteger('egress_bytes')->default(0);
            $table->unsignedBigInteger('rated_bytes')->default(0);
            $table->unsignedBigInteger('billable_bytes')->default(0);
            $table->unsignedBigInteger('accrued_amount')->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['virtual_machine_id', 'period_start'], 'vm_net_period_vm_start_unique');
            $table->index(['period_end', 'status']);
        });

        Schema::create('network_meter_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('assignment_id', 128);
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('vm_uuid');
            $table->timestamp('first_observed_at');
            $table->timestamp('last_observed_at');
            $table->timestamps();
            $table->unique(['source', 'assignment_id'], 'net_assignment_source_id_unique');
            $table->index(['vm_uuid', 'first_observed_at']);
        });

        Schema::create('network_usage_buckets', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('bucket_id', 128);
            $table->unsignedInteger('revision');
            $table->string('status', 16);
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('vm_uuid');
            $table->string('assignment_id', 128);
            $table->timestamp('interval_start');
            $table->timestamp('interval_end');
            $table->unsignedBigInteger('ingress_bytes');
            $table->unsignedBigInteger('egress_bytes');
            $table->string('completeness', 16);
            $table->string('calculation_version', 80)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('source_updated_at');
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('processing_status', 20)->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'bucket_id']);
            $table->index(['processing_status', 'status']);
            $table->index(['vm_uuid', 'interval_start']);
        });

        Schema::create('network_usage_bucket_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_usage_bucket_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['network_usage_bucket_id', 'revision'], 'net_bucket_revision_unique');
        });

        Schema::create('network_usage_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_usage_bucket_id')->constrained()->restrictOnDelete();
            $table->foreignId('vm_network_billing_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedBigInteger('ingress_bytes');
            $table->unsignedBigInteger('egress_bytes');
            $table->unsignedBigInteger('rated_bytes');
            $table->bigInteger('amount_delta');
            $table->unsignedBigInteger('period_amount_after');
            $table->json('policy_snapshot');
            $table->foreignId('usage_accrual_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['network_usage_bucket_id', 'revision'], 'net_rating_bucket_revision_unique');
        });

        Schema::create('network_ingestion_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->unique();
            $table->text('cursor')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_ingestion_checkpoints');
        Schema::dropIfExists('network_usage_ratings');
        Schema::dropIfExists('network_usage_bucket_revisions');
        Schema::dropIfExists('network_usage_buckets');
        Schema::dropIfExists('network_meter_assignments');
        Schema::dropIfExists('vm_network_billing_periods');

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropColumn([
                'network_accounting_enabled_override', 'network_included_bytes_monthly_override',
                'network_overage_price_override', 'network_overage_price_unit_bytes_override',
                'network_usage_direction_override', 'network_billing_timezone_override',
            ]);
        });
        Schema::table('vm_bundles', function (Blueprint $table): void {
            $table->dropColumn([
                'network_accounting_enabled', 'network_included_bytes_monthly', 'network_overage_price',
                'network_overage_price_unit_bytes', 'network_usage_direction', 'network_billing_timezone',
            ]);
        });
    }
};
