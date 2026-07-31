<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboard_warning_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('warning_key', 64);
            $table->timestamps();

            $table->unique(['user_id', 'warning_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboard_warning_dismissals');
    }
};
