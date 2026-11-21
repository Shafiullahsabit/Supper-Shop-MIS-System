<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogBranchRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_migration_restores_missing_branch_column_for_logout_logs(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Schema::table('activity_logs', function ($table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        $migration = require database_path('migrations/2026_11_19_000200_repair_activity_logs_branch_id.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('activity_logs', 'branch_id'));

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'logout',
            'branch_id' => $user->branch_id,
        ]);
    }
}
