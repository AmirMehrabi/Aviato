<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->string('team_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('contact_submissions')->whereNull('team_size')->update(['team_size' => 'نامشخص']);

        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->string('team_size')->nullable(false)->change();
        });
    }
};
