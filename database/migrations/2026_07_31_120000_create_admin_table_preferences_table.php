<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_table_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 80);
            $table->string('sort_column', 80);
            $table->string('sort_direction', 4);
            $table->timestamps();

            $table->unique(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_table_preferences');
    }
};
