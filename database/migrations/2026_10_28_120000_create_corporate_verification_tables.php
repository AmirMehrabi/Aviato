<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('national_id', 11)->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(0);
            $table->json('company');
            $table->json('registry_result');
            $table->timestamp('looked_up_at');
            $table->string('representative_role')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_number')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });
        Schema::create('corporate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind');
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
        });
        Schema::create('corporate_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('event');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::create('corporate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('national_id', 11)->unique();
            $table->foreignId('corporate_request_id')->constrained();
            $table->json('company');
            $table->timestamp('approved_at');
            $table->timestamp('billing_effective_at');
            $table->timestamps();
        });
        Schema::create('billing_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('effective_at')->index();
            $table->text('identity');
            $table->timestamps();
        });
        Schema::create('corporate_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_request_event_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel');
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['corporate_request_event_id', 'recipient_type', 'recipient_id', 'channel'], 'corporate_delivery_unique');
        });
        foreach (['invoices', 'payments', 'domain_orders'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->text('billing_identity')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'payments', 'domain_orders'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('billing_identity'));
        }
        foreach (['corporate_deliveries', 'billing_identities', 'corporate_profiles', 'corporate_request_events', 'corporate_documents', 'corporate_requests'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
