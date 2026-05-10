<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_machines', 'iso_volume')) {
                $table->string('iso_volume')->nullable()->after('os_template');
            }

            if (! Schema::hasColumn('virtual_machines', 'network_bridge')) {
                $table->string('network_bridge')->nullable()->after('iso_volume');
            }
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_machines', 'network_bridge')) {
                $table->dropColumn('network_bridge');
            }

            if (Schema::hasColumn('virtual_machines', 'iso_volume')) {
                $table->dropColumn('iso_volume');
            }
        });
    }
};
