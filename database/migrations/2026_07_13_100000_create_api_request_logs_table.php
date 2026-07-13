<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->string('token_fingerprint', 16)->nullable()->index();
            $table->string('method', 10);
            $table->string('route')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->string('failure_type')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('query')->nullable();
            $table->unsignedBigInteger('request_bytes')->nullable();
            $table->unsignedBigInteger('response_bytes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['route', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
