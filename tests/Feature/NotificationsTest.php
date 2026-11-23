<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_and_manage_their_own_notifications(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['is_active' => true]);
        $user->syncRoles('Admin');
        $otherUser->syncRoles('Admin');

        $user->notify($this->notification('Private update'));
        $otherUser->notify($this->notification('Another user update'));

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Private update')
            ->assertDontSeeText('Another user update')
            ->assertSee('notification-bell-count');

        $notification = $user->unreadNotifications()->firstOrFail();
        $this->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($user->fresh()->notifications()->findOrFail($notification->id)->read_at);

        $otherNotification = $otherUser->unreadNotifications()->firstOrFail();
        $this->post(route('notifications.read', $otherNotification->id))->assertNotFound();

        $user->notify($this->notification('Second update'));
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_crossing_a_branch_low_stock_threshold_notifies_only_relevant_staff_and_does_not_repeat(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = Branch::default();
        $otherBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
        $branchAdmin = $this->userInBranch($branch, 'Admin');
        $otherBranchAdmin = $this->userInBranch($otherBranch, 'Admin');
        $superAdmin = $this->userInBranch($branch, 'Super Admin');
        $product = Product::create([
            'name' => 'Low Stock Test Product',
            'sku' => 'NOTIFY-001',
            'cost_price' => 1,
            'sale_price' => 2,
            'is_active' => true,
        ]);
        BranchInventory::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock_quantity' => 6,
            'alert_quantity' => 5,
        ]);

        $this->actingAs($branchAdmin);
        $sale = [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ];
        app(SaleService::class)->checkout($sale, $branchAdmin->id);

        $this->assertNotificationEvent($branchAdmin, 'low_stock');
        $this->assertNotificationEvent($superAdmin, 'low_stock');
        $this->assertSame(0, $otherBranchAdmin->notifications()->count());

        app(SaleService::class)->checkout($sale, $branchAdmin->id);
        $this->assertSame(1, $branchAdmin->notifications()->count());
    }

    private function notification(string $title): OperationalNotification
    {
        return new OperationalNotification([
            'event' => 'test',
            'category' => 'test',
            'title' => $title,
            'message' => $title,
            'url' => route('notifications.index'),
            'icon' => 'info',
        ]);
    }

    private function userInBranch(Branch $branch, string $role): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $user->syncRoles($role);

        return $user;
    }

    private function assertNotificationEvent(User $user, string $event): void
    {
        $this->assertTrue($user->notifications()->get()->contains(
            fn ($notification) => ($notification->data['event'] ?? null) === $event
        ));
    }
}
