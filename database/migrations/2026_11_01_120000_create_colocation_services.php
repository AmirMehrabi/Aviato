<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('colocation_enabled')->default(false);
        });
        Schema::create('colocation_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('location');
            $table->unsignedSmallInteger('rack_units');
            $table->string('power');
            $table->string('connectivity');
            $table->string('traffic_policy');
            $table->unsignedInteger('included_traffic_gb')->default(0);
            $table->unsignedBigInteger('traffic_price_per_gb')->default(0);
            $table->string('currency', 10);
            $table->unsignedBigInteger('monthly_price')->nullable();
            $table->unsignedBigInteger('quarterly_price')->nullable();
            $table->unsignedBigInteger('yearly_price')->nullable();
            $table->unsignedBigInteger('setup_fee')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->text('terms');
            $table->text('delivery_instructions')->nullable();
            $table->boolean('available')->default(true);
            $table->timestamps();
        });
        Schema::create('colocation_services', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('checkout_key')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('colocation_plan_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('awaiting_payment')->index();
            $table->string('billing_interval');
            $table->json('agreement');
            $table->timestamp('accepted_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('billing_starts_at')->nullable();
            $table->timestamp('paid_through')->nullable();
            $table->unsignedInteger('next_period')->default(1);
            $table->text('equipment')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('period_start')->nullable()->change();
            $table->timestamp('period_end')->nullable()->change();
            $table->foreignId('colocation_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('colocation_key')->nullable()->unique();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('invoice_items', fn (Blueprint $table) => $table->decimal('unit_price', 20, 6)->default(0)->change());
        Schema::create('colocation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colocation_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('invoices')->where('kind', 'colocation')->exists() || DB::table('colocation_services')->exists()) {
            throw new RuntimeException('Archive co-location services and financial records before rolling back co-location billing.');
        }
        // Retain widened invoice-item precision so rollback cannot truncate historical prices.
        Schema::dropIfExists('colocation_events');
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('invoice_id'));
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('colocation_service_id');
            $table->dropUnique(['colocation_key']);
            $table->dropColumn(['colocation_key', 'due_at', 'paid_at']);
            $table->timestamp('period_start')->nullable(false)->change();
            $table->timestamp('period_end')->nullable(false)->change();
        });
        Schema::dropIfExists('colocation_services');
        Schema::dropIfExists('colocation_plans');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('colocation_enabled'));
    }
};
