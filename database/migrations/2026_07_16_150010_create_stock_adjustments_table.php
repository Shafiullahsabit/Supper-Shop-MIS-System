<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['add', 'subtract']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            $table->string('reason'); // damaged, lost, correction, expired, found
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
