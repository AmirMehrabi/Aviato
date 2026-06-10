<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_customer_id')->constrained('customers');
            $table->foreignId('to_customer_id')->constrained('customers');
            $table->foreignId('from_project_id')->nullable()->constrained('projects');
            $table->foreignId('to_project_id')->nullable()->constrained('projects');
            $table->foreignId('initiated_by_user_id')->constrained('users');
            $table->integer('unbilled_amount_transferred')->default(0);
            $table->text('notes')->nullable();
            $table->json('snapshot_before')->nullable();
            $table->json('snapshot_after')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_transfers');
    }
};