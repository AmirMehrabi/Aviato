<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxmox_servers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cpu_threshold_percent')->default(80);
            $table->unsignedTinyInteger('ram_threshold_percent')->default(85);
            $table->unsignedTinyInteger('disk_threshold_percent')->default(80);
            $table->json('api_endpoints')->nullable();
        });

        Schema::create('cloud_image_node_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cloud_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_server_id')->constrained()->cascadeOnDelete();
            $table->string('node');
            $table->unsignedBigInteger('template_vmid');
            $table->string('storage')->nullable();
            $table->string('network_bridge')->default('vmbr1');
            $table->string('template_version')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->json('verification_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['cloud_image_id', 'node'], 'cloud_image_node_unique');
            $table->index(['proxmox_server_id', 'node', 'is_enabled'], 'image_node_placement_idx');
        });

        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->foreignId('cloud_image_node_mapping_id')
                ->nullable()
                ->after('cloud_image_id')
                ->constrained('cloud_image_node_mappings')
                ->nullOnDelete();
            $table->json('placement_snapshot')->nullable()->after('provider_metadata');
        });

        $now = now();

        DB::table('cloud_images')
            ->whereNotNull('proxmox_server_id')
            ->whereNotNull('node')
            ->whereNotNull('template_vmid')
            ->orderBy('id')
            ->each(function (object $image) use ($now): void {
                DB::table('cloud_image_node_mappings')->updateOrInsert(
                    ['cloud_image_id' => $image->id, 'node' => $image->node],
                    [
                        'proxmox_server_id' => $image->proxmox_server_id,
                        'template_vmid' => $image->template_vmid,
                        'storage' => $image->storage,
                        'network_bridge' => $image->network_bridge ?: 'vmbr1',
                        'is_enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cloud_image_node_mapping_id');
            $table->dropColumn('placement_snapshot');
        });

        Schema::dropIfExists('cloud_image_node_mappings');

        Schema::table('proxmox_servers', function (Blueprint $table): void {
            $table->dropColumn([
                'cpu_threshold_percent',
                'ram_threshold_percent',
                'disk_threshold_percent',
                'api_endpoints',
            ]);
        });
    }
};
