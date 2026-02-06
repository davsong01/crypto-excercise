<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();               // BTC, ETH, USDT
            $table->string('name')->nullable();              // Bitcoin, Ethereum
            $table->decimal('fee', 10, 2)->default(0);       // transaction fee, naira
            $table->enum('fee_type', ['fixed','percentage'])->default('percentage');
            $table->decimal('min_trade_amount', 36, 18);

            $table->timestamps();
        });

        DB::table('trade_currencies')->insert([
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'fee' => 1.5,
                'fee_type' => 'percentage',
                'min_trade_amount' => 0.00000000001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'fee' => 1.2,
                'fee_type' => 'percent',
                'min_trade_amount' => 0.00000000001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'symbol' => 'USDT',
                'name' => 'Tether',
                'fee' => 1000,
                'fee_type' => 'fixed',
                'min_trade_amount' => 0.00000000001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_currencies');
    }
};
