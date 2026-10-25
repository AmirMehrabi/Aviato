<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_products', function (Blueprint $table): void {
            $table->id();
            $table->string('extension', 80);
            $table->string('operation', 20);
            $table->unsignedTinyInteger('period');
            $table->string('contact_mode')->default('contact');
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('supplier_price')->nullable();
            $table->unsignedBigInteger('retail_price')->nullable();
            $table->unsignedBigInteger('minimum_margin')->default(0);
            $table->unsignedInteger('markup_percent')->default(25);
            $table->boolean('automatic_pricing')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['extension', 'operation', 'period']);
        });
        Schema::create('domain_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->text('details');
            $table->timestamps();
        });
        Schema::create('managed_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('name', 253)->unique();
            $table->string('extension', 80);
            $table->string('status')->default('active');
            $table->string('provider_id')->nullable();
            $table->text('registrant')->nullable();
            $table->json('nameservers');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('auto_renew_limit')->nullable();
            $table->timestamp('auto_renew_consented_at')->nullable();
            $table->timestamps();
        });
        Schema::create('domain_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('managed_domain_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('domain_product_id')->constrained()->restrictOnDelete();
            $table->string('domain', 253);
            $table->string('operation', 20);
            $table->unsignedTinyInteger('period');
            $table->string('contact_mode')->default('contact');
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->default('IRR');
            $table->unsignedBigInteger('supplier_price');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('tax_amount');
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->unsignedBigInteger('total');
            $table->timestamp('quoted_until');
            $table->text('registrant')->nullable();
            $table->json('nameservers')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expiry_before')->nullable();
            $table->string('customer_message', 600)->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'domain']);
        });
        Schema::create('domain_claims', function (Blueprint $table): void {
            $table->string('domain', 253)->primary();
            $table->foreignId('domain_order_id')->unique()->constrained()->restrictOnDelete();
        });
        Schema::create('domain_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('domain_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('managed_domain_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('status')->default('submitting')->index();
            $table->string('track_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('result_code')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['domain_order_id', 'kind']);
        });
        Schema::create('domain_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('managed_domain_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->timestamps();
            $table->unique(['managed_domain_id', 'key']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('domain_order_id')->nullable()->constrained()->restrictOnDelete();
        });
        // Domain invoices are per order, while usage invoices retain their period uniqueness.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('kind')->default('usage')->index();
            $table->string('billing_key')->nullable()->default('usage');
            $table->foreignId('domain_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unique(['customer_id', 'period_start', 'period_end', 'billing_key'], 'invoices_period_billing_unique');
            $table->dropUnique(['customer_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['domain_order_id']);
            $table->dropUnique(['domain_order_id']);
            $table->unique(['customer_id', 'period_start', 'period_end']);
            $table->dropUnique('invoices_period_billing_unique');
            $table->dropColumn(['domain_order_id', 'kind', 'billing_key']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('domain_order_id'));
        Schema::dropIfExists('domain_notices');
        Schema::dropIfExists('domain_operations');
        Schema::dropIfExists('domain_claims');
        Schema::dropIfExists('domain_orders');
        Schema::dropIfExists('managed_domains');
        Schema::dropIfExists('domain_contacts');
        Schema::dropIfExists('domain_products');
    }
};
