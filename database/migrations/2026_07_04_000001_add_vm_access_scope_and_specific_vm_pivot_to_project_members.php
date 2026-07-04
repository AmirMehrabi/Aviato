<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_members', 'vm_access_scope')) {
                $table->string('vm_access_scope', 20)->default('all')->after('role');
            }
        });

        if (! Schema::hasTable('project_member_virtual_machines')) {
            Schema::create('project_member_virtual_machines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_member_id')->constrained('project_members')->cascadeOnDelete();
                $table->foreignId('virtual_machine_id')->constrained('virtual_machines')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['project_member_id', 'virtual_machine_id']);
            });
        }

        DB::table('project_members')
            ->where('role', 'member')
            ->where(function ($query): void {
                $query->whereNull('vm_access_scope')
                    ->orWhere('vm_access_scope', '');
            })
            ->update(['vm_access_scope' => 'own']);
    }

    public function down(): void
    {
        if (Schema::hasTable('project_member_virtual_machines')) {
            Schema::dropIfExists('project_member_virtual_machines');
        }

        Schema::table('project_members', function (Blueprint $table): void {
            if (Schema::hasColumn('project_members', 'vm_access_scope')) {
                $table->dropColumn('vm_access_scope');
            }
        });
    }
};
