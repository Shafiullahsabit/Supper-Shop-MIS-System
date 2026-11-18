<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('branch_number', 32)->unique();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'Main Branch',
            'branch_number' => '001',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('users', function (Blueprint $table) use ($branchId) {
            $table->foreignId('branch_id')->default($branchId)->constrained()->restrictOnDelete();
        });
        DB::table('users')->update(['branch_id' => $branchId]);

        Schema::create('branch_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('alert_quantity', 12, 3)->default(5);
            $table->timestamps();
            $table->unique(['branch_id', 'product_id']);
        });

        DB::table('products')->orderBy('id')->chunk(500, function ($products) use ($branchId, $now): void {
            $inventories = [];
            foreach ($products as $product) {
                $inventories[] = [
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'stock_quantity' => $product->stock_quantity,
                    'alert_quantity' => $product->alert_quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($inventories) {
                DB::table('branch_inventories')->insert($inventories);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'alert_quantity']);
        });

        foreach (['sales', 'purchases', 'cash_sessions', 'stock_adjustments', 'held_sales', 'activity_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($branchId) {
                $table->foreignId('branch_id')->default($branchId)->constrained()->restrictOnDelete();
            });
            DB::table($tableName)->update(['branch_id' => $branchId]);
        }
    }

    public function down(): void
    {
        $branchId = DB::table('branches')->where('branch_number', '001')->value('id');

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('alert_quantity', 12, 3)->default(5);
        });

        if ($branchId) {
            DB::table('branch_inventories')
                ->where('branch_id', $branchId)
                ->orderBy('product_id')
                ->chunk(500, function ($inventories): void {
                    foreach ($inventories as $inventory) {
                        DB::table('products')->where('id', $inventory->product_id)->update([
                            'stock_quantity' => $inventory->stock_quantity,
                            'alert_quantity' => $inventory->alert_quantity,
                        ]);
                    }
                });
        }

        foreach (['sales', 'purchases', 'cash_sessions', 'stock_adjustments', 'held_sales', 'activity_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::dropIfExists('branch_inventories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
