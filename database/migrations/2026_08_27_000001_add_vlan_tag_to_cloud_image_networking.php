<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_images', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vlan_tag')->nullable()->after('network_bridge');
        });

        Schema::table('cloud_image_node_mappings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vlan_tag')->nullable()->after('network_bridge');
        });

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vlan_tag')->nullable()->after('network_bridge');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', fn (Blueprint $table) => $table->dropColumn('vlan_tag'));
        Schema::table('cloud_image_node_mappings', fn (Blueprint $table) => $table->dropColumn('vlan_tag'));
        Schema::table('cloud_images', fn (Blueprint $table) => $table->dropColumn('vlan_tag'));
    }
};
