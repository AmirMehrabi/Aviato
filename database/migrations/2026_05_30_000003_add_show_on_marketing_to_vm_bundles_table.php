<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_bundles', function (Blueprint $table): void {
            $table->boolean('show_on_marketing')
                ->default(true)
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('vm_bundles', function (Blueprint $table): void {
            $table->dropColumn('show_on_marketing');
        });
    }
};
