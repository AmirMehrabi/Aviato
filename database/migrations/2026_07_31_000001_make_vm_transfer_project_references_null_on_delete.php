<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_transfers', function (Blueprint $table): void {
            $table->dropForeign(['from_project_id']);
            $table->dropForeign(['to_project_id']);
            $table->foreign('from_project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('to_project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vm_transfers', function (Blueprint $table): void {
            $table->dropForeign(['from_project_id']);
            $table->dropForeign(['to_project_id']);
            $table->foreign('from_project_id')->references('id')->on('projects');
            $table->foreign('to_project_id')->references('id')->on('projects');
        });
    }
};
