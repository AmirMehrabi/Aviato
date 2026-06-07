<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('round_robin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['support_team_id', 'user_id']);
        });

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_strategy')->default('round_robin');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->string('source')->default('customer');
            $table->timestamp('last_customer_reply_at')->nullable();
            $table->timestamp('last_admin_reply_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['customer_id', 'status']);
            $table->index(['support_team_id', 'assigned_user_id']);
            $table->index('last_activity_at');
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('author');
            $table->string('type')->default('reply');
            $table->longText('body');
            $table->timestamp('seen_by_customer_at')->nullable();
            $table->timestamp('seen_by_admin_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'type']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('actor');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'type']);
        });

        DB::table('ticket_categories')->insert([
            'name' => 'General Support',
            'slug' => 'general-support',
            'description' => 'Default intake category for customer support tickets.',
            'assignment_strategy' => 'manual',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('support_team_user');
        Schema::dropIfExists('support_teams');
    }
};
