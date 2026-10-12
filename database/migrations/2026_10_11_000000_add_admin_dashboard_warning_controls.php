<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_dashboard_warning_dismissals', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable();
        });
        Schema::create('admin_dashboard_warning_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('warning_type');
            $table->timestamps();
            $table->unique(['user_id', 'warning_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboard_warning_preferences');
        Schema::table('admin_dashboard_warning_dismissals', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
