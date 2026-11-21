<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiBranchSchemaRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_migration_restores_missing_branch_columns_and_dashboard_access(): void
    {
        $tables = [
            'users',
            'sales',
            'purchases',
            'cash_sessions',
            'stock_adjustments',
            'held_sales',
            'activity_logs',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        $migration = require database_path('migrations/2026_11_19_000300_repair_branch_scoped_columns.php');
        $migration->up();

        foreach ($tables as $tableName) {
            $this->assertTrue(Schema::hasColumn($tableName, 'branch_id'), $tableName);
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
