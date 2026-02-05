<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trade_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();
            $table->string('name');
            $table->enum('fee_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('fee_value', 10, 2)->default(0.5);
            $table->decimal('min_trade_amount', 20, 8)->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_currencies');
    }
};
