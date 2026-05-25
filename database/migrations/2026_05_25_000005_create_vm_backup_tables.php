<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_backup_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('frequency')->default('daily');
            $table->time('preferred_time')->default('02:00');
            $table->unsignedSmallInteger('retention_count')->default(3);
            $table->string('backup_storage')->nullable();
            $table->string('mode')->default('snapshot');
            $table->string('compression')->default('zstd');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique('virtual_machine_id');
            $table->index(['is_enabled', 'next_run_at']);
        });

        Schema::create('vm_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vm_backup_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('status')->default('queued');
            $table->string('proxmox_task_id')->nullable();
            $table->string('node')->nullable();
            $table->string('storage')->nullable();
            $table->string('volid')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('error')->nullable();
            $table->json('remote_state')->nullable();
            $table->timestamps();

            $table->index(['virtual_machine_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->unique('volid');
        });

        if (Schema::hasTable('resource_rates') && ! DB::table('resource_rates')->where('resource', 'backup_gb')->exists()) {
            DB::table('resource_rates')->insert([
                'resource' => 'backup_gb',
                'label' => 'Backup Storage',
                'unit' => 'GB',
                'hourly_price' => 6000 / 730,
                'monthly_price' => 6000,
                'billing_policy' => 'always',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_backups');
        Schema::dropIfExists('vm_backup_policies');
    }
};
