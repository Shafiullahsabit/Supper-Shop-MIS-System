<?php

namespace Database\Seeders;

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
        'inventory.view', 'inventory.adjust',
        'sales.view', 'sales.refund',
        'cash.manage',
        'reports.view',
        'users.manage', 'roles.manage',
        'settings.manage', 'backups.manage', 'activity.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Admin — everything
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->syncPermissions(Permission::all());

        // Manager — operations, no user/role/backup control
        $manager = Role::findOrCreate('Manager', 'web');
        $manager->syncPermissions([
            'dashboard.view', 'pos.access',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.manage', 'units.manage', 'suppliers.manage',
            'customers.view', 'customers.manage',
            'purchases.view', 'purchases.manage',
            'inventory.view', 'inventory.adjust',
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
