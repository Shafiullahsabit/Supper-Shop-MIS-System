<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchLowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_threshold_is_updated_only_for_the_active_branch_and_drives_dashboard_alerts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $mainBranch = Branch::default();
        $secondBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'branch_id' => $mainBranch->id,
            'is_active' => true,
        ]);
        $admin->syncRoles('Admin');
        $product = Product::create([
            'name' => 'Threshold Test Product',
            'sku' => 'THRESHOLD-001',
            'cost_price' => 1,
            'sale_price' => 2,
            'is_active' => true,
        ]);
        BranchInventory::create([
            'branch_id' => $mainBranch->id,
            'product_id' => $product->id,
            'stock_quantity' => 8,
            'alert_quantity' => 2,
        ]);
        BranchInventory::create([
            'branch_id' => $secondBranch->id,
            'product_id' => $product->id,
            'stock_quantity' => 8,
            'alert_quantity' => 1,
        ]);

        $this->actingAs($admin)->put(route('inventory.threshold', $product), [
            'alert_quantity' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $mainBranch->id,
            'product_id' => $product->id,
            'alert_quantity' => 10,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $secondBranch->id,
            'product_id' => $product->id,
            'alert_quantity' => 1,
        ]);

        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Threshold Test Product')
            ->assertSee('Low-stock threshold');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Threshold Test Product')
            ->assertSee(__('Low Stock Alerts'));
    }
}
