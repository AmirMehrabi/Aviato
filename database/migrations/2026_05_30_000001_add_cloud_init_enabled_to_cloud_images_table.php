<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_images', function (Blueprint $table) {
            $table->boolean('cloud_init_enabled')->default(true)->after('ostype');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_images', function (Blueprint $table) {
            $table->dropColumn('cloud_init_enabled');
        });
    }
};
