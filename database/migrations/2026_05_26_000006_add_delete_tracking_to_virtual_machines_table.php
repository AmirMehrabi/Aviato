<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->timestamp('delete_requested_at')->nullable()->after('last_stopped_at');
            $table->timestamp('delete_started_at')->nullable()->after('delete_requested_at');
            $table->timestamp('deleted_at')->nullable()->after('delete_started_at');
            $table->timestamp('delete_failed_at')->nullable()->after('deleted_at');
            $table->text('delete_error')->nullable()->after('delete_failed_at');
            $table->string('delete_task_id')->nullable()->after('delete_error');

            $table->index(['customer_id', 'deleted_at']);
            $table->index(['status', 'delete_requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'deleted_at']);
            $table->dropIndex(['status', 'delete_requested_at']);
            $table->dropColumn([
                'delete_requested_at',
                'delete_started_at',
                'deleted_at',
                'delete_failed_at',
                'delete_error',
                'delete_task_id',
            ]);
        });
    }
};
