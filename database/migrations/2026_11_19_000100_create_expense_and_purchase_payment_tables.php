<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('description', 191);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('status', 20)->default('unpaid');
            $table->date('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'expense_date']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30)->nullable();
            $table->timestamp('paid_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'paid_at']);
        });

        DB::table('purchases')
            ->where('paid', '>', 0)
            ->orderBy('id')
            ->chunk(500, function ($purchases): void {
                $payments = [];

                foreach ($purchases as $purchase) {
                    $payments[] = [
                        'purchase_id' => $purchase->id,
                        'user_id' => null,
                        'amount' => $purchase->paid,
                        'payment_method' => null,
                        'paid_at' => $purchase->purchase_date.' 00:00:00',
                        'note' => 'Imported from the existing paid amount.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($payments) {
                    DB::table('purchase_payments')->insert($payments);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
