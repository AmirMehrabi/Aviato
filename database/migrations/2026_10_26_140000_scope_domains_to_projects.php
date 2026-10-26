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
        foreach (['managed_domains', 'domain_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
                $table->index(['project_id', 'status']);
            });
        }

        $this->backfill();
    }

    public function backfill(): void
    {
        DB::table('customers')->select('id')->orderBy('id')->chunkById(200, function ($customers): void {
            foreach ($customers as $customer) {
                if (! DB::table('managed_domains')->where('customer_id', $customer->id)->exists()
                    && ! DB::table('domain_orders')->where('customer_id', $customer->id)->exists()) {
                    continue;
                }
                $projectId = DB::table('projects')->where('owner_customer_id', $customer->id)->orderBy('created_at')->orderBy('id')->value('id');
                if (! $projectId) {
                    $projectId = DB::table('projects')->insertGetId([
                        'uuid' => (string) Str::uuid(), 'owner_customer_id' => $customer->id,
                        'name' => 'فضای کاری من', 'slug' => 'personal-workspace', 'is_default' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('project_members')->insertOrIgnore([
                    'project_id' => $projectId, 'customer_id' => $customer->id, 'role' => 'owner',
                    'vm_access_scope' => 'all', 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (['managed_domains', 'domain_orders'] as $tableName) {
                    DB::table($tableName)->where('customer_id', $customer->id)->whereNull('project_id')->update(['project_id' => $projectId]);
                }
            }
        });
        // A renewal or historical order always follows its existing domain.
        DB::table('domain_orders')->whereNotNull('managed_domain_id')->update([
            'project_id' => DB::table('managed_domains')->select('project_id')->whereColumn('managed_domains.id', 'domain_orders.managed_domain_id'),
        ]);
    }

    public function down(): void
    {
        foreach (['domain_orders', 'managed_domains'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['project_id', 'status']);
                $table->dropConstrainedForeignId('project_id');
            });
        }
    }
};
