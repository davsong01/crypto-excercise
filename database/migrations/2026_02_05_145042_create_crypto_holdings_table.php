<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trade_currency_id')->constrained('trade_currencies')->onDelete('cascade');
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'trade_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_holdings');
    }
};

