<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'sales',
        'purchases',
        'cash_sessions',
        'stock_adjustments',
        'held_sales',
        'activity_logs',
    ];

    public function up(): void
    {
        $branchId = DB::table('branches')->orderBy('id')->value('id');
        if (! $branchId) {
            throw new RuntimeException('Cannot repair branch-scoped tables because no branch exists.');
        }

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                throw new RuntimeException("Cannot repair branch-scoped table {$tableName} because it does not exist.");
            }

            if (! Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($branchId): void {
                    $table->foreignId('branch_id')->default($branchId)->constrained('branches')->restrictOnDelete();
                });
            } else {
                DB::table($tableName)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void {}
};
