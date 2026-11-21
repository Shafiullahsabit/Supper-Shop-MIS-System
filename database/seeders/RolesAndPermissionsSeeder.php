<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission catalog grouped by module. Each entry becomes a permission
     * and drives the sidebar / gate checks across the app.
     */
    public const PERMISSIONS = [
        'dashboard.view',
        'pos.access',
        'products.view', 'products.create', 'products.edit', 'products.delete',
        'categories.manage', 'units.manage', 'suppliers.manage',
        'customers.view', 'customers.manage',
        'purchases.view', 'purchases.manage',
        'expenses.view', 'expenses.manage',
        'inventory.view', 'inventory.adjust', 'inventory.transfers', 'inventory.transfers.approve',
        'sales.view', 'sales.refund',
        'cash.manage',
        'reports.view',
        'users.manage', 'roles.manage',
        'settings.manage', 'backups.manage', 'activity.view',
        'branches.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Admin — all permissions within their assigned branch
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->syncPermissions(Permission::whereNotIn('name', ['branches.manage', 'inventory.transfers.approve'])->get());

        // Super Admin — global access across all branches
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Manager — operations, no user/role/backup control
        $manager = Role::findOrCreate('Manager', 'web');
        $manager->syncPermissions([
            'dashboard.view', 'pos.access',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.manage', 'units.manage', 'suppliers.manage',
            'customers.view', 'customers.manage',
            'purchases.view', 'purchases.manage',
            'expenses.view', 'expenses.manage',
            'inventory.view', 'inventory.adjust', 'inventory.transfers',
            'sales.view', 'sales.refund', 'cash.manage',
            'reports.view', 'activity.view',
        ]);

        // Cashier — POS front line
        $cashier = Role::findOrCreate('Cashier', 'web');
        $cashier->syncPermissions([
            'dashboard.view', 'pos.access',
            'products.view', 'customers.view', 'customers.manage',
            'sales.view', 'cash.manage',
        ]);

        if (! User::role('Super Admin')->exists()) {
            $firstAdmin = User::role('Admin')->oldest('id')->first();
            $firstAdmin?->syncRoles('Super Admin');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
