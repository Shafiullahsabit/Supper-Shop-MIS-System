<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            throw new RuntimeException('Cannot repair activity logs because the table does not exist.');
        }

        if (Schema::hasColumn('activity_logs', 'branch_id')) {
            return;
        }

        $branchId = DB::table('branches')->orderBy('id')->value('id');
        if (! $branchId) {
            throw new RuntimeException('Cannot repair activity logs because no branch exists.');
        }

        Schema::table('activity_logs', function (Blueprint $table) use ($branchId): void {
            $table->foreignId('branch_id')->default($branchId)->constrained('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'branch_id')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
