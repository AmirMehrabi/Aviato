<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_machines', 'mac_address')) {
                $table->string('mac_address', 17)->nullable()->after('network_bridge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_machines', 'mac_address')) {
                $table->dropColumn('mac_address');
            }
        });
    }
};
