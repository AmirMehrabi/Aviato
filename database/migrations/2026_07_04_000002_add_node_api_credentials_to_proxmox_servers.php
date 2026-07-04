<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxmox_servers', function (Blueprint $table): void {
            $table->text('node_api_credentials')->nullable()->after('api_endpoints');
        });
    }

    public function down(): void
    {
        Schema::table('proxmox_servers', function (Blueprint $table): void {
            $table->dropColumn('node_api_credentials');
        });
    }
};
