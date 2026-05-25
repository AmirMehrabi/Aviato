<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->foreignId('cloud_image_id')->nullable()->after('vm_bundle_id')->constrained()->nullOnDelete();
            $table->foreignId('ip_address_id')->nullable()->after('cloud_image_id')->constrained('ip_addresses')->nullOnDelete();
            $table->unsignedBigInteger('template_vmid')->nullable()->after('vmid');
            $table->string('login_username')->nullable()->after('ip_address');
            $table->text('login_password')->nullable()->after('login_username');
            $table->text('ssh_public_key')->nullable()->after('login_password');
            $table->string('provisioning_job_id')->nullable()->after('remote_state');
            $table->string('provisioning_task_id')->nullable()->after('provisioning_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cloud_image_id');
            $table->dropConstrainedForeignId('ip_address_id');
            $table->dropColumn([
                'template_vmid',
                'login_username',
                'login_password',
                'ssh_public_key',
                'provisioning_job_id',
                'provisioning_task_id',
            ]);
        });
    }
};
