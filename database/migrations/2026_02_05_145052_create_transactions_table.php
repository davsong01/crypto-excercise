<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdraw', 'buy', 'sell']);
            $table->foreignId('trade_currency_id')->nullable()->constrained('trade_currencies')->onDelete('set null');
            $table->decimal('fee', 14, 2)->default(0);
            $table->decimal('fee_rate', 8, 2)->nullable();
            $table->enum('fee_rate_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('crypto_amount', 24, 8)->default(0);
            $table->string('reference')->nullable();
            $table->string('conversion_rate')->nullable();
            $table->string('status')->default('initiated');
            $table->string('duplicate_check')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
