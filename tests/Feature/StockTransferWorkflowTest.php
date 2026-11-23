<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private Product $product;

    private User $sourceAdmin;

    private User $destinationAdmin;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->sourceBranch = Branch::default();
        $this->destinationBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TRANSFER-001',
            'cost_price' => 2,
            'sale_price' => 4,
            'is_active' => true,
        ]);
        BranchInventory::create([
            'branch_id' => $this->sourceBranch->id,
            'product_id' => $this->product->id,
            'stock_quantity' => 10,
            'alert_quantity' => 2,
        ]);
        BranchInventory::create([
            'branch_id' => $this->destinationBranch->id,
            'product_id' => $this->product->id,
            'stock_quantity' => 1,
            'alert_quantity' => 2,
        ]);

        $this->sourceAdmin = $this->userInBranch($this->sourceBranch, 'Admin');
        $this->destinationAdmin = $this->userInBranch($this->destinationBranch, 'Admin');
        $this->superAdmin = $this->userInBranch($this->sourceBranch, 'Super Admin');
    }

    public function test_transfer_request_requires_super_admin_approval_before_stock_can_be_dispatched(): void
    {
        $transfer = $this->createTransfer(4);
        $this->assertNotificationEvent($this->superAdmin, 'transfer_requested');
        $this->assertSame(StockTransfer::STATUS_PENDING_APPROVAL, $transfer->status);
        $this->assertSame(10.0, $this->stock($this->sourceBranch));
        $this->assertSame(1.0, $this->stock($this->destinationBranch));

        $this->actingAs($this->sourceAdmin)
            ->post(route('transfers.approve', $transfer))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('transfers.approve', $transfer))
            ->assertRedirect();
        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransfer::STATUS_APPROVED,
            'approved_by' => $this->superAdmin->id,
        ]);
        $this->assertNotificationEvent($this->sourceAdmin, 'transfer_approved');

        $this->actingAs($this->destinationAdmin)
            ->post(route('transfers.dispatch', $transfer))
            ->assertNotFound();

        $this->actingAs($this->sourceAdmin)
            ->post(route('transfers.dispatch', $transfer))
            ->assertRedirect();
        $this->assertSame(6.0, $this->stock($this->sourceBranch));
        $this->assertSame(1.0, $this->stock($this->destinationBranch));
        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransfer::STATUS_IN_TRANSIT,
        ]);
        $this->assertNotificationEvent($this->destinationAdmin, 'transfer_dispatched');
    }

    public function test_partial_receipts_add_only_received_stock_and_can_complete_in_multiple_deliveries(): void
    {
        $transfer = $this->approveAndDispatch(5);

        $this->actingAs($this->destinationAdmin)
            ->post(route('transfers.receive', $transfer), [
                'items' => [
                    $transfer->items->first()->id => [
                        'transfer_item_id' => $transfer->items->first()->id,
                        'quantity_received' => 2,
                    ],
                ],
            ])
            ->assertRedirect(route('transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_PARTIALLY_RECEIVED, $transfer->status);
        $this->assertSame(3.0, $this->stock($this->destinationBranch));
        $this->assertNotificationEvent($this->sourceAdmin, 'transfer_received');

        $this->actingAs($this->destinationAdmin)
            ->post(route('transfers.receive', $transfer), [
                'items' => [
                    $transfer->items->first()->id => [
                        'transfer_item_id' => $transfer->items->first()->id,
                        'quantity_received' => 3,
                    ],
                ],
            ])
            ->assertRedirect(route('transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->status);
        $this->assertSame(6.0, $this->stock($this->destinationBranch));
        $this->assertSame(2, $transfer->receipts()->count());
    }

    public function test_final_shortage_requires_a_reason_and_records_the_discrepancy(): void
    {
        $transfer = $this->approveAndDispatch(5);
        $item = $transfer->items->first();

        $this->actingAs($this->destinationAdmin)
            ->post(route('transfers.receive', $transfer), [
                'close_transfer' => 1,
                'items' => [
                    $item->id => [
                        'transfer_item_id' => $item->id,
                        'quantity_received' => 3,
                    ],
                ],
            ])
            ->assertSessionHasErrors("items.{$item->id}.discrepancy_reason");

        $this->assertSame(1.0, $this->stock($this->destinationBranch));

        $this->actingAs($this->destinationAdmin)
            ->post(route('transfers.receive', $transfer), [
                'close_transfer' => 1,
                'items' => [
                    $item->id => [
                        'transfer_item_id' => $item->id,
                        'quantity_received' => 3,
                        'discrepancy_reason' => 'Two units were damaged in transit.',
                    ],
                ],
            ])
            ->assertRedirect(route('transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY, $transfer->status);
        $this->assertSame(4.0, $this->stock($this->destinationBranch));
        $this->assertDatabaseHas('stock_transfer_receipt_items', [
            'stock_transfer_item_id' => $item->id,
            'quantity_received' => 3,
            'discrepancy_reason' => 'Two units were damaged in transit.',
        ]);
        $this->assertNotificationEvent($this->sourceAdmin, 'transfer_received');
    }

    public function test_dispatch_rechecks_stock_and_never_partially_deducts_an_unavailable_transfer(): void
    {
        $secondTransfer = $this->createTransfer(3);
        $transfer = $this->approveAndDispatch(8);

        $this->actingAs($this->superAdmin)->post(route('transfers.approve', $secondTransfer));
        $this->actingAs($this->sourceAdmin)
            ->post(route('transfers.dispatch', $secondTransfer))
            ->assertSessionHasErrors('transfer');

        $secondTransfer->refresh();
        $this->assertSame(StockTransfer::STATUS_APPROVED, $secondTransfer->status);
        $this->assertSame(2.0, $this->stock($this->sourceBranch));
        $this->assertSame(1.0, $this->stock($this->destinationBranch));
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->refresh()->status);
    }

    public function test_transfer_list_and_details_are_limited_to_the_users_branches(): void
    {
        $transfer = $this->createTransfer(2);
        $otherBranch = Branch::create([
            'name' => 'South Store',
            'branch_number' => '003',
            'is_active' => true,
        ]);
        $outsider = $this->userInBranch($otherBranch, 'Admin');

        $this->actingAs($this->destinationAdmin)
            ->get(route('transfers.index'))
            ->assertOk()
            ->assertSeeText($transfer->reference);

        $this->actingAs($outsider)
            ->get(route('transfers.show', $transfer))
            ->assertNotFound();
    }

    public function test_request_detail_and_receiving_pages_render_for_their_authorized_branches(): void
    {
        $this->actingAs($this->sourceAdmin)
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSeeText($this->sourceBranch->name)
            ->assertSeeText($this->product->name);

        $transfer = $this->createTransfer(2);
        $this->actingAs($this->superAdmin)
            ->get(route('transfers.show', $transfer))
            ->assertOk()
            ->assertSeeText($transfer->reference)
            ->assertSeeText('Approve');

        $transfer = $this->approveAndDispatch(2);
        $this->actingAs($this->destinationAdmin)
            ->get(route('transfers.receive.form', $transfer))
            ->assertOk()
            ->assertSeeText('Received now')
            ->assertSeeText('Close transfer after this receipt');
    }

    public function test_super_admin_can_reject_with_a_reason_and_source_can_cancel_before_dispatch(): void
    {
        $rejected = $this->createTransfer(2);
        $this->actingAs($this->superAdmin)
            ->post(route('transfers.reject', $rejected), ['rejection_reason' => 'Stock is needed at the source.'])
            ->assertRedirect();
        $this->assertSame(StockTransfer::STATUS_REJECTED, $rejected->refresh()->status);
        $this->assertNotificationEvent($this->sourceAdmin, 'transfer_rejected');
        $this->assertSame(10.0, $this->stock($this->sourceBranch));

        $cancelled = $this->createTransfer(3);
        $this->actingAs($this->sourceAdmin)
            ->post(route('transfers.cancel', $cancelled))
            ->assertRedirect();
        $this->assertSame(StockTransfer::STATUS_CANCELLED, $cancelled->refresh()->status);
        $this->assertNotificationEvent($this->destinationAdmin, 'transfer_cancelled');
        $this->assertSame(10.0, $this->stock($this->sourceBranch));
    }

    private function createTransfer(int $quantity): StockTransfer
    {
        $response = $this->actingAs($this->sourceAdmin)->post(route('transfers.store'), [
            'destination_branch_id' => $this->destinationBranch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity],
            ],
        ]);

        $response->assertRedirect();

        return StockTransfer::query()->latest('id')->firstOrFail();
    }

    private function approveAndDispatch(int $quantity): StockTransfer
    {
        $transfer = $this->createTransfer($quantity);
        $this->actingAs($this->superAdmin)->post(route('transfers.approve', $transfer))->assertRedirect();
        $this->actingAs($this->sourceAdmin)->post(route('transfers.dispatch', $transfer))->assertRedirect();

        return $transfer->load('items');
    }

    private function userInBranch(Branch $branch, string $role): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function stock(Branch $branch): float
    {
        return (float) BranchInventory::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $this->product->id)
            ->value('stock_quantity');
    }

    private function assertNotificationEvent(User $user, string $event): void
    {
        $this->assertTrue($user->notifications()->get()->contains(
            fn ($notification) => ($notification->data['event'] ?? null) === $event
        ));
    }
}
