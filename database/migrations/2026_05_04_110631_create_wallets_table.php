<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->string('lock_reason')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->index(['balance', 'is_locked']);
        });

        DB::table('customers')->orderBy('id')->select('id')->chunk(500, function ($customers): void {
            foreach ($customers as $customer) {
                DB::table('wallets')->insert([
                    'customer_id' => $customer->id,
                    'balance' => 0,
                    'is_locked' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
