<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // ---- Users ----
        $admin = User::updateOrCreate(
            ['email' => 'admin@supershop.test'],
            ['name' => 'Store Owner', 'password' => Hash::make('password'), 'phone' => '0170000001', 'is_active' => true]
        );
        $admin->syncRoles('Admin');

        $manager = User::updateOrCreate(
            ['email' => 'manager@supershop.test'],
            ['name' => 'Floor Manager', 'password' => Hash::make('password'), 'phone' => '0170000002', 'is_active' => true]
        );
        $manager->syncRoles('Manager');

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@supershop.test'],
            ['name' => 'Front Cashier', 'password' => Hash::make('password'), 'phone' => '0170000003', 'is_active' => true]
        );
        $cashier->syncRoles('Cashier');

        // ---- Units ----
        $pcs = Unit::firstOrCreate(['short_name' => 'pcs'], ['name' => 'Pieces', 'allow_decimal' => false]);
        $kg = Unit::firstOrCreate(['short_name' => 'kg'], ['name' => 'Kilogram', 'allow_decimal' => true]);
        $ltr = Unit::firstOrCreate(['short_name' => 'ltr'], ['name' => 'Litre', 'allow_decimal' => true]);
        $pack = Unit::firstOrCreate(['short_name' => 'pack'], ['name' => 'Pack', 'allow_decimal' => false]);

        // ---- Categories ----
        $cats = [
            ['Groceries', '#2563eb'], ['Beverages', '#0ea5e9'], ['Snacks', '#f59e0b'],
            ['Dairy', '#10b981'], ['Bakery', '#ef4444'], ['Household', '#8b5cf6'],
            ['Personal Care', '#ec4899'], ['Frozen', '#06b6d4'],
        ];
        $categories = [];
        foreach ($cats as [$name, $color]) {
            $categories[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'color' => $color, 'is_active' => true]
            );
        }

        // ---- Supplier ----
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Metro Wholesale'],
            ['company' => 'Metro Wholesale Ltd', 'email' => 'sales@metro.test', 'phone' => '0180000000', 'is_active' => true]
        );

        // ---- Products (with real-looking barcodes) ----
        $products = [
            ['Basmati Rice 5kg', 'Groceries', $kg, 6.50, 9.99, 5, 40, '8901234500017'],
            ['Sunflower Oil 1L', 'Groceries', $ltr, 2.10, 3.49, 0, 60, '8901234500024'],
            ['White Sugar 1kg', 'Groceries', $kg, 0.80, 1.29, 0, 80, '8901234500031'],
            ['Coca-Cola 2L', 'Beverages', $pcs, 1.10, 1.99, 0, 50, '5449000000996'],
            ['Mineral Water 1.5L', 'Beverages', $pcs, 0.30, 0.75, 0, 120, '8901234500055'],
            ['Orange Juice 1L', 'Beverages', $ltr, 1.40, 2.49, 0, 30, '8901234500062'],
            ['Potato Chips 150g', 'Snacks', $pcs, 0.90, 1.75, 0, 45, '8901234500079'],
            ['Chocolate Bar 100g', 'Snacks', $pcs, 0.60, 1.20, 0, 70, '8901234500086'],
            ['Salted Peanuts 200g', 'Snacks', $pcs, 0.75, 1.50, 0, 40, '8901234500093'],
            ['Fresh Milk 1L', 'Dairy', $ltr, 0.85, 1.40, 0, 35, '8901234500109'],
            ['Cheddar Cheese 250g', 'Dairy', $pcs, 2.20, 3.75, 0, 20, '8901234500116'],
            ['Greek Yogurt 500g', 'Dairy', $pcs, 1.30, 2.25, 0, 25, '8901234500123'],
            ['Whole Wheat Bread', 'Bakery', $pcs, 0.70, 1.35, 0, 30, '8901234500130'],
            ['Butter Croissant', 'Bakery', $pcs, 0.45, 0.99, 0, 24, '8901234500147'],
            ['Dish Wash Liquid 500ml', 'Household', $pcs, 1.05, 1.99, 0, 40, '8901234500154'],
            ['Laundry Detergent 1kg', 'Household', $kg, 2.80, 4.50, 0, 25, '8901234500161'],
            ['Paper Towels 4pk', 'Household', $pack, 1.60, 2.99, 0, 30, '8901234500178'],
            ['Toothpaste 100g', 'Personal Care', $pcs, 0.95, 1.89, 0, 50, '8901234500185'],
            ['Hand Soap 250ml', 'Personal Care', $pcs, 0.85, 1.65, 0, 45, '8901234500192'],
            ['Shampoo 400ml', 'Personal Care', $pcs, 2.10, 3.99, 0, 20, '8901234500208'],
            ['Frozen Pizza', 'Frozen', $pcs, 2.50, 4.25, 0, 18, '8901234500215'],
            ['Ice Cream 1L', 'Frozen', $ltr, 2.00, 3.50, 0, 22, '8901234500222'],
            ['Frozen Peas 500g', 'Frozen', $pcs, 0.90, 1.60, 0, 28, '8901234500239'],
            ['Instant Coffee 200g', 'Beverages', $pcs, 3.20, 5.49, 0, 15, '8901234500246'],
        ];

        $sku = 1000;
        foreach ($products as [$name, $cat, $unit, $cost, $price, $tax, $stock, $barcode]) {
            Product::firstOrCreate(
                ['barcode' => $barcode],
                [
                    'name' => $name,
                    'sku' => 'SKU-'.($sku++),
                    'category_id' => $categories[$cat]->id,
                    'unit_id' => $unit->id,
                    'supplier_id' => $supplier->id,
                    'cost_price' => $cost,
                    'sale_price' => $price,
                    'tax_rate' => $tax,
                    'stock_quantity' => $stock,
                    'alert_quantity' => 10,
                    'is_active' => true,
                ]
            );
        }

        // ---- Walk-in customer + samples ----
        Customer::firstOrCreate(['phone' => '0000000000'], ['name' => 'Walk-in Customer', 'is_active' => true]);
        Customer::firstOrCreate(['phone' => '0171111111'], ['name' => 'John Doe', 'email' => 'john@example.com', 'loyalty_points' => 120]);
        Customer::firstOrCreate(['phone' => '0172222222'], ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'loyalty_points' => 340]);

        // ---- Settings ----
        $settings = [
            'shop_name' => 'SuperShop',
            'shop_address' => '123 Market Street, Downtown',
            'shop_phone' => '+1 (555) 010-2030',
            'shop_email' => 'hello@supershop.test',
            'currency_symbol' => '$',
            'currency_position' => 'before',
            'tax_inclusive' => '0',
            'default_tax_rate' => '0',
            'receipt_footer' => 'Thank you for shopping with us!',
            'loyalty_earn_rate' => '1',
            'low_stock_alert' => '1',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
