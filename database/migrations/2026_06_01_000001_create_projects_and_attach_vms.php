<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['owner_customer_id', 'slug']);
            $table->index(['owner_customer_id', 'is_default']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('role', 30)->default('member');
            $table->timestamps();

            $table->unique(['project_id', 'customer_id']);
            $table->index(['customer_id', 'role']);
        });

        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by_customer_id')->nullable()->after('project_id')->constrained('customers')->nullOnDelete();
            $table->index(['project_id', 'status']);
            $table->index(['created_by_customer_id', 'created_at']);
        });

        DB::table('customers')->orderBy('id')->select('id', 'name')->chunk(500, function ($customers): void {
            foreach ($customers as $customer) {
                $projectId = DB::table('projects')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'owner_customer_id' => $customer->id,
                    'name' => 'Default Project',
                    'slug' => 'default-project',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('project_members')->insert([
                    'project_id' => $projectId,
                    'customer_id' => $customer->id,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('virtual_machines')
                    ->where('customer_id', $customer->id)
                    ->whereNull('project_id')
                    ->update([
                        'project_id' => $projectId,
                        'created_by_customer_id' => DB::raw('customer_id'),
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['created_by_customer_id', 'created_at']);
            $table->dropConstrainedForeignId('created_by_customer_id');
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
