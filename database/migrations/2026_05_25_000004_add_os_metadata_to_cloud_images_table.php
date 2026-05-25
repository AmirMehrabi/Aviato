<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_images', function (Blueprint $table) {
            $table->string('os_family')->default('ubuntu')->after('description');
            $table->string('os_version')->nullable()->after('os_family');
            $table->string('logo_key')->default('ubuntu')->after('os_version');

            $table->index(['os_family', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('cloud_images', function (Blueprint $table) {
            $table->dropIndex(['os_family', 'is_active', 'sort_order']);
            $table->dropColumn(['os_family', 'os_version', 'logo_key']);
        });
    }
};
