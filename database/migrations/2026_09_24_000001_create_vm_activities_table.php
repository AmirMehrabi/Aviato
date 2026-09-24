<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('virtual_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('event', 40);
            $table->string('outcome', 20);
            $table->string('title', 160);
            $table->text('detail')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['virtual_machine_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_activities');
    }
};
