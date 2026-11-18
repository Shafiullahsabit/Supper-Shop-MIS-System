<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('card_sales', 12, 2)->default(0);
            $table->decimal('other_sales', 12, 2)->default(0);
            $table->decimal('cash_refunds', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
