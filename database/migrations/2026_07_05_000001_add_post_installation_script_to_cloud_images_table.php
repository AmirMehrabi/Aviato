<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_images', function (Blueprint $table): void {
            $table->text('post_installation_script')->nullable()->after('default_username');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_images', function (Blueprint $table): void {
            $table->dropColumn('post_installation_script');
        });
    }
};
