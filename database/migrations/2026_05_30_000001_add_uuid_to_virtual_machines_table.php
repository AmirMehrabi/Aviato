<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('virtual_machines', 'uuid')) {
            Schema::table('virtual_machines', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table('virtual_machines')
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($virtualMachines): void {
                foreach ($virtualMachines as $virtualMachine) {
                    DB::table('virtual_machines')
                        ->where('id', $virtualMachine->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('virtual_machines', 'uuid')) {
            return;
        }

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
