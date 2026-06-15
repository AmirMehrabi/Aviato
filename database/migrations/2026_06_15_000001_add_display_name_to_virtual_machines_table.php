<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        DB::table('virtual_machines')
            ->whereNull('display_name')
            ->update(['display_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
