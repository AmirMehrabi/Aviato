<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_buckets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 63)->unique();
            $table->string('region', 32)->default('aviato-1');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedBigInteger('usage_bytes')->default(0);
            $table->unsignedBigInteger('object_count')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('storage_access_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('access_key_id', 32)->unique();
            $table->text('secret_encrypted');
            $table->string('description', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('storage_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_bucket_id')->constrained()->cascadeOnDelete();
            $table->string('object_key', 1024);
            $table->char('object_key_hash', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('etag', 128);
            $table->string('content_type', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('storage_path', 2048);
            $table->timestamps();

            $table->unique(['storage_bucket_id', 'object_key_hash'], 'storage_objects_bucket_key_unique');
            $table->index(['storage_bucket_id', 'created_at']);
        });

        Schema::create('storage_multipart_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_bucket_id')->constrained()->cascadeOnDelete();
            $table->uuid('upload_id')->unique();
            $table->string('object_key', 1024);
            $table->char('object_key_hash', 64);
            $table->string('content_type', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['storage_bucket_id', 'object_key_hash', 'status'], 'storage_uploads_bucket_object_status_idx');
        });

        Schema::create('storage_multipart_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_multipart_upload_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('part_number');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('etag', 128);
            $table->string('storage_path', 2048);
            $table->timestamps();

            $table->unique(['storage_multipart_upload_id', 'part_number'], 'storage_multipart_parts_upload_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_multipart_parts');
        Schema::dropIfExists('storage_multipart_uploads');
        Schema::dropIfExists('storage_objects');
        Schema::dropIfExists('storage_access_keys');
        Schema::dropIfExists('storage_buckets');
    }
};
