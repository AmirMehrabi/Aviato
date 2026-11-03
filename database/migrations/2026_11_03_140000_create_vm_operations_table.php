<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('active_vm_id')->nullable()->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('action');
            $table->string('status')->default('queued');
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('task_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['virtual_machine_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_operations');
    }
};
