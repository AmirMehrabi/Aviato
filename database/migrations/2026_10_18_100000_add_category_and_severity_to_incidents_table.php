<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('category', 32)->default('compute')->index();
            $table->string('severity', 16)->default('warning');
        });

        // Preserve legacy reports while recognizing their existing service names.
        foreach (['network' => ['Network', 'شبکه', 'شبکه و ارتباطات'], 'panel' => ['Panel', 'API', 'پنل کاربری', 'پنل کاربری و API']] as $category => $names) {
            DB::table('incidents')->whereIn('affected_service', $names)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'severity']);
        });
    }
};
