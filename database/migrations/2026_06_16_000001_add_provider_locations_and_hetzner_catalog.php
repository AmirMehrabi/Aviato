<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hetzner_accounts')) {
            Schema::create('hetzner_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('api_token');
                $table->boolean('is_active')->default(true);
                $table->boolean('maintenance_mode')->default(false);
                $table->json('remote_inventory')->nullable();
                $table->string('connection_status')->default('unknown');
                $table->string('sync_status')->default('pending');
                $table->text('sync_error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'maintenance_mode']);
                $table->index(['connection_status', 'sync_status']);
            });
        }

        if (! Schema::hasTable('infrastructure_locations')) {
            Schema::create('infrastructure_locations', function (Blueprint $table): void {
                $table->id();
                $table->string('provider');
                $table->foreignId('proxmox_server_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('hetzner_account_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('region')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('remote_id')->nullable();
                $table->string('remote_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('maintenance_mode')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'hetzner_account_id', 'remote_id'], 'infra_locations_provider_account_remote_unique');
                $table->index(['provider', 'is_active', 'maintenance_mode'], 'infra_locations_provider_active_idx');
                $table->index(['proxmox_server_id'], 'infra_locations_proxmox_idx');
                $table->index(['hetzner_account_id'], 'infra_locations_hetzner_idx');
            });
        }

        $this->ensureIndex('infrastructure_locations', 'infra_locations_provider_active_idx', ['provider', 'is_active', 'maintenance_mode']);
        $this->ensureIndex('infrastructure_locations', 'infra_locations_proxmox_idx', ['proxmox_server_id']);
        $this->ensureIndex('infrastructure_locations', 'infra_locations_hetzner_idx', ['hetzner_account_id']);

        if (! Schema::hasTable('hetzner_locations')) {
            Schema::create('hetzner_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hetzner_account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('infrastructure_location_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('remote_id')->nullable();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('network_zone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('raw')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['hetzner_account_id', 'name']);
                $table->index(['hetzner_account_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hetzner_images')) {
            Schema::create('hetzner_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hetzner_account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cloud_image_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('remote_id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('type')->nullable();
                $table->string('architecture')->nullable();
                $table->string('os_flavor')->nullable();
                $table->string('os_version')->nullable();
                $table->boolean('deprecated')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('raw')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['hetzner_account_id', 'remote_id']);
                $table->index(['hetzner_account_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hetzner_server_types')) {
            Schema::create('hetzner_server_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hetzner_account_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('remote_id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('architecture')->nullable();
                $table->unsignedSmallInteger('cpu_cores')->default(1);
                $table->decimal('memory_gb', 8, 2)->default(1);
                $table->unsignedInteger('disk_gb')->default(10);
                $table->json('prices')->nullable();
                $table->json('available_locations')->nullable();
                $table->boolean('deprecated')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('raw')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['hetzner_account_id', 'remote_id']);
                $table->index(['hetzner_account_id', 'is_active']);
                $table->index(['name', 'architecture']);
            });
        }

        if (! Schema::hasTable('vm_bundle_location_mappings')) {
            Schema::create('vm_bundle_location_mappings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vm_bundle_id');
                $table->unsignedBigInteger('infrastructure_location_id');
                $table->unsignedBigInteger('hetzner_server_type_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->decimal('monthly_price_usd', 12, 4)->nullable();
                $table->unsignedBigInteger('monthly_price_irr')->nullable();
                $table->unsignedBigInteger('usd_to_irr_rate')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('price_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['vm_bundle_id', 'infrastructure_location_id'], 'bundle_location_unique');
                $table->index(['infrastructure_location_id', 'is_active'], 'bundle_location_active_idx');
                $table->foreign('vm_bundle_id', 'bundle_location_bundle_fk')->references('id')->on('vm_bundles')->cascadeOnDelete();
                $table->foreign('infrastructure_location_id', 'bundle_location_location_fk')->references('id')->on('infrastructure_locations')->cascadeOnDelete();
                $table->foreign('hetzner_server_type_id', 'bundle_location_hcloud_type_fk')->references('id')->on('hetzner_server_types')->nullOnDelete();
            });
        }

        $this->ensureIndex('vm_bundle_location_mappings', 'bundle_location_active_idx', ['infrastructure_location_id', 'is_active']);

        Schema::table('cloud_images', function (Blueprint $table): void {
            if (! Schema::hasColumn('cloud_images', 'infrastructure_location_id')) {
                $table->foreignId('infrastructure_location_id')->nullable()->after('proxmox_server_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cloud_images', 'provider')) {
                $table->string('provider')->default('proxmox')->after('infrastructure_location_id');
            }
            if (! Schema::hasColumn('cloud_images', 'remote_image_id')) {
                $table->string('remote_image_id')->nullable()->after('template_vmid');
            }
            if (! Schema::hasColumn('cloud_images', 'remote_architecture')) {
                $table->string('remote_architecture')->nullable()->after('remote_image_id');
            }
            if (! Schema::hasColumn('cloud_images', 'provider_metadata')) {
                $table->json('provider_metadata')->nullable()->after('remote_architecture');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE cloud_images MODIFY proxmox_server_id BIGINT UNSIGNED NULL');
        }

        Schema::table('virtual_machines', function (Blueprint $table): void {
            if (! Schema::hasColumn('virtual_machines', 'infrastructure_location_id')) {
                $table->foreignId('infrastructure_location_id')->nullable()->after('proxmox_server_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('virtual_machines', 'provider')) {
                $table->string('provider')->default('proxmox')->after('infrastructure_location_id');
            }
            if (! Schema::hasColumn('virtual_machines', 'remote_id')) {
                $table->string('remote_id')->nullable()->after('vmid');
            }
            if (! Schema::hasColumn('virtual_machines', 'remote_name')) {
                $table->string('remote_name')->nullable()->after('remote_id');
            }
            if (! Schema::hasColumn('virtual_machines', 'remote_region')) {
                $table->string('remote_region')->nullable()->after('remote_name');
            }
            if (! Schema::hasColumn('virtual_machines', 'provider_metadata')) {
                $table->json('provider_metadata')->nullable()->after('remote_region');
            }
        });

        $this->backfillProxmoxLocations();
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            foreach (['provider_metadata', 'remote_region', 'remote_name', 'remote_id', 'provider', 'infrastructure_location_id'] as $column) {
                if (Schema::hasColumn('virtual_machines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('cloud_images', function (Blueprint $table): void {
            foreach (['provider_metadata', 'remote_architecture', 'remote_image_id', 'provider', 'infrastructure_location_id'] as $column) {
                if (Schema::hasColumn('cloud_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('vm_bundle_location_mappings');
        Schema::dropIfExists('hetzner_server_types');
        Schema::dropIfExists('hetzner_images');
        Schema::dropIfExists('hetzner_locations');
        Schema::dropIfExists('infrastructure_locations');
        Schema::dropIfExists('hetzner_accounts');
    }

    private function backfillProxmoxLocations(): void
    {
        $now = now();

        DB::table('proxmox_servers')
            ->orderBy('id')
            ->get()
            ->each(function (object $server) use ($now): void {
                $slug = 'proxmox-'.$server->id;
                $existingLocation = DB::table('infrastructure_locations')
                    ->where('provider', 'proxmox')
                    ->where('proxmox_server_id', $server->id)
                    ->first();

                $locationId = $existingLocation?->id ?: DB::table('infrastructure_locations')->insertGetId([
                    'provider' => 'proxmox',
                    'proxmox_server_id' => $server->id,
                    'name' => $server->datacenter ?: $server->name,
                    'slug' => $slug,
                    'region' => $server->datacenter,
                    'remote_id' => (string) $server->id,
                    'remote_name' => $server->name,
                    'is_active' => (bool) $server->is_active,
                    'maintenance_mode' => (bool) $server->maintenance_mode,
                    'metadata' => json_encode([
                        'cluster_name' => $server->cluster_name,
                        'environment' => $server->environment,
                        'host' => $server->host,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('cloud_images')
                    ->where('proxmox_server_id', $server->id)
                    ->update([
                        'infrastructure_location_id' => $locationId,
                        'provider' => 'proxmox',
                    ]);

                DB::table('virtual_machines')
                    ->where('proxmox_server_id', $server->id)
                    ->update([
                        'infrastructure_location_id' => $locationId,
                        'provider' => 'proxmox',
                        'remote_id' => DB::raw('vmid'),
                    ]);
            });
    }

    private function ensureIndex(string $tableName, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($tableName) || $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('".$tableName."')"))
                ->contains(fn (object $index): bool => ($index->name ?? null) === $indexName);
        }

        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
