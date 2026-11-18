<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Support\BranchContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $mainBranch;

    private Branch $secondBranch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->mainBranch = Branch::default();
        $this->secondBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'cost_price' => 1,
            'sale_price' => 2,
            'is_active' => true,
        ]);

        BranchInventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_id' => $this->product->id,
            'stock_quantity' => 10,
            'alert_quantity' => 2,
        ]);
        BranchInventory::create([
            'branch_id' => $this->secondBranch->id,
            'product_id' => $this->product->id,
            'stock_quantity' => 20,
            'alert_quantity' => 2,
        ]);
    }

    public function test_sales_and_stock_are_isolated_by_branch_but_visible_to_super_admin(): void
    {
        $cashierOne = $this->userInBranch($this->mainBranch, 'Cashier');
        $cashierTwo = $this->userInBranch($this->secondBranch, 'Cashier');

        $this->actingAs($cashierOne);
        $sale = app(SaleService::class)->checkout([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ], $cashierOne->id);

        $this->assertSame(8.0, $this->inventory($this->mainBranch)->stock_quantity + 0.0);
        $this->assertSame(20.0, $this->inventory($this->secondBranch)->stock_quantity + 0.0);

        $this->actingAs($cashierTwo);
        $this->assertSame(0, Sale::count());
        $this->get(route('sales.show', $sale))->assertNotFound();

        $superAdmin = $this->userInBranch($this->mainBranch, 'Super Admin');
        $this->actingAs($superAdmin);
        $this->assertSame(1, Sale::count());
        $this->get(route('sales.show', $sale))->assertOk();

        $this->post(route('branches.switch'), ['branch_id' => $this->secondBranch->id])->assertRedirect();
        $this->assertSame($this->secondBranch->id, BranchContext::id());
        app(SaleService::class)->checkout([
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ], $superAdmin->id);
        $this->assertSame(19.0, (float) $this->inventory($this->secondBranch)->stock_quantity);
    }

    public function test_branch_admin_can_only_create_staff_in_their_own_branch(): void
    {
        $admin = $this->userInBranch($this->mainBranch, 'Admin');

        $this->actingAs($admin)->post(route('branches.store'), [
            'name' => 'Blocked Branch',
            'branch_number' => '004',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Main Cashier',
            'email' => 'main-cashier@example.test',
            'password' => 'secret123',
            'role' => 'Cashier',
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'main-cashier@example.test',
            'branch_id' => $this->mainBranch->id,
        ]);
    }

    public function test_super_admin_can_create_a_branch_with_zeroed_stock(): void
    {
        $superAdmin = $this->userInBranch($this->mainBranch, 'Super Admin');

        $this->actingAs($superAdmin)->get(route('branches.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('activity.index'))->assertOk();
        $this->get(route('sales.index'))->assertOk();
        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('register.index'))->assertOk();
        $this->get(route('reports.inventory'))->assertOk();

        $this->actingAs($superAdmin)->post(route('branches.store'), [
            'name' => 'Exact User-Entered Name',
            'branch_number' => '003',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $branch = Branch::where('branch_number', '003')->firstOrFail();
        $this->assertSame('Exact User-Entered Name', $branch->name);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'product_id' => $this->product->id,
            'stock_quantity' => 0,
        ]);

        $this->post(route('branches.switch'), ['branch_id' => $branch->id])->assertRedirect();
        $this->put(route('branches.update', $branch), [
            'name' => $branch->name,
            'branch_number' => $branch->branch_number,
            'address' => '',
            'phone' => '',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(session()->has('active_branch_id'));
    }

    public function test_product_creation_initializes_stock_only_in_the_current_branch(): void
    {
        $admin = $this->userInBranch($this->mainBranch, 'Admin');

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Branch Stock Product',
            'sku' => 'BRANCH-001',
            'cost_price' => 3,
            'sale_price' => 5,
            'stock_quantity' => 7,
            'alert_quantity' => 2,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('sku', 'BRANCH-001')->firstOrFail();
        $this->assertSame(7.0, (float) BranchInventory::where('branch_id', $this->mainBranch->id)
            ->where('product_id', $product->id)->value('stock_quantity'));
        $this->assertSame(0.0, (float) BranchInventory::where('branch_id', $this->secondBranch->id)
            ->where('product_id', $product->id)->value('stock_quantity'));
    }

    public function test_offline_super_admin_sale_keeps_its_captured_branch(): void
    {
        $superAdmin = $this->userInBranch($this->mainBranch, 'Super Admin');

        $this->actingAs($superAdmin)->postJson(route('pos.sync'), [
            'sales' => [[
                'client_uuid' => 'offline-branch-test',
                'branch_id' => $this->secondBranch->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ]],
        ])->assertOk()->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('sales', [
            'client_uuid' => 'offline-branch-test',
            'branch_id' => $this->secondBranch->id,
        ]);
        $this->assertSame(19.0, (float) $this->inventory($this->secondBranch)->stock_quantity);
        $this->assertSame(10.0, (float) $this->inventory($this->mainBranch)->stock_quantity);
    }

    private function userInBranch(Branch $branch, string $role): User
    {
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->syncRoles($role);

        return $user;
    }

    private function inventory(Branch $branch): BranchInventory
    {
        return BranchInventory::where('branch_id', $branch->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
    }
}
