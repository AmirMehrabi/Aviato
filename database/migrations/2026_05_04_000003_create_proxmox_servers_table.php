<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cluster_name')->nullable();
            $table->string('datacenter')->nullable();
            $table->string('environment')->default('production');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(8006);
            $table->string('realm')->default('pam');
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('api_token_id')->nullable();
            $table->text('api_token_secret')->nullable();
            $table->boolean('verify_tls')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->json('tags')->nullable();
            $table->json('desired_state')->nullable();
            $table->json('remote_inventory')->nullable();
            $table->string('connection_status')->default('unknown');
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('sync_pending_since')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('last_status')->nullable();
            $table->timestamps();

            $table->unique(['host', 'port', 'username', 'api_token_id']);
            $table->index(['cluster_name', 'is_active']);
            $table->index(['datacenter', 'environment']);
            $table->index(['connection_status', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_servers');
    }
};
