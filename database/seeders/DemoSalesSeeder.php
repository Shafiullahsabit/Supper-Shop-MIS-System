<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSalesSeeder extends Seeder
{
    /**
     * Generate a spread of realistic completed sales over the last 10 days
     * so the dashboard, charts and reports have meaningful data to show.
     */
    public function run(): void
    {
        $cashiers = User::role(['Cashier', 'Manager', 'Admin'])->get();
        $products = Product::where('is_active', true)->get();
        $customers = Customer::where('phone', '!=', '0000000000')->get();
        $methods = ['cash', 'cash', 'cash', 'card', 'card', 'mobile'];

        if ($cashiers->isEmpty() || $products->isEmpty()) {
            return;
        }

        for ($day = 10; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day);
            $orders = random_int(3, 9); // orders that day

            for ($i = 0; $i < $orders; $i++) {
                $when = (clone $date)->addHours(random_int(9, 20))->addMinutes(random_int(0, 59));
                $cashier = $cashiers->random();
                $lineCount = random_int(1, 5);
                $picked = $products->random(min($lineCount, $products->count()));

                $subtotal = 0;
                $taxTotal = 0;
                $lines = [];
                foreach ($picked as $product) {
                    $qty = $product->unit && $product->unit->allow_decimal ? random_int(1, 4) : random_int(1, 3);
                    $price = (float) $product->sale_price;
                    $base = $price * $qty;
                    $tax = round($base * ((float) $product->tax_rate / 100), 2);
                    $subtotal += $base;
                    $taxTotal += $tax;
                    $lines[] = compact('product', 'qty', 'price', 'tax', 'base');
                }

                $discount = random_int(0, 4) === 0 ? round($subtotal * 0.05, 2) : 0;
                $total = round($subtotal - $discount + $taxTotal, 2);
                $method = $methods[array_rand($methods)];
                $paid = $method === 'cash' ? ceil($total) : $total;

                $sale = Sale::create([
                    'invoice_no' => Sale::generateInvoiceNo(),
                    'customer_id' => random_int(0, 1) ? optional($customers->random())->id : null,
                    'user_id' => $cashier->id,
                    'subtotal' => round($subtotal, 2),
                    'discount' => $discount,
                    'tax' => round($taxTotal, 2),
                    'total' => $total,
                    'paid' => $paid,
                    'change_due' => round($paid - $total, 2),
                    'payment_method' => $method,
                    'status' => 'completed',
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);

                foreach ($lines as $l) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $l['product']->id,
                        'name' => $l['product']->name,
                        'price' => $l['price'],
                        'cost' => (float) $l['product']->cost_price,
                        'quantity' => $l['qty'],
                        'discount' => 0,
                        'tax' => $l['tax'],
                        'subtotal' => round($l['base'] + $l['tax'], 2),
                        'created_at' => $when,
                        'updated_at' => $when,
                    ]);
                }
            }
        }
    }
}
