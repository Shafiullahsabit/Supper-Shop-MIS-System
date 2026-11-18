<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Persist a completed POS sale inside a single transaction:
     * validates stock, writes sale + items, decrements inventory,
     * awards loyalty points and rolls totals into the cashier's cash session.
     *
     * @param  array  $payload  {items:[{product_id,quantity,price,discount}], customer_id, payment_method, paid, discount, note, client_uuid, offline_created_at, is_offline}
     * @param  array  $options  {allow_oversell:bool} — allow_oversell completes the sale even when
     *                          current stock is short (offline sale of goods that already left the shop);
     *                          stock is driven negative and flagged for the owner to reconcile.
     */
    public function checkout(array $payload, int $userId, array $options = []): Sale
    {
        $allowOversell = (bool) ($options['allow_oversell'] ?? false);
        $clientUuid = $payload['client_uuid'] ?? null;
        $requestedBranchId = $options['branch_id'] ?? null;
        if ($requestedBranchId !== null && ! auth()->user()?->hasRole('Super Admin')) {
            abort(403);
        }

        $branchId = $requestedBranchId ? (int) $requestedBranchId : BranchContext::id();
        if ($requestedBranchId && ! Branch::whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch' => __('Select an active branch before creating a sale.')]);
        }

        if (! $branchId) {
            throw ValidationException::withMessages(['branch' => __('Select an active branch before creating a sale.')]);
        }

        // Idempotency: a queued offline sale that is re-sent must never be inserted twice.
        if ($clientUuid) {
            $existing = Sale::where('client_uuid', $clientUuid)->where('branch_id', $branchId)->first();
            if ($existing) {
                return $existing->load('items', 'customer', 'cashier');
            }
        }

        return DB::transaction(function () use ($payload, $userId, $allowOversell, $clientUuid, $branchId) {
            $lines = collect($payload['items'] ?? []);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('The cart is empty.')]);
            }

            $oversold = [];

            $orderDiscount = round((float) ($payload['discount'] ?? 0), 2);
            $subtotal = 0;
            $taxTotal = 0;
            $lineDiscountTotal = 0;
            $preparedItems = [];

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = Product::lockForUpdate()->find($line['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => __('A product in the cart no longer exists.')]);
                }
                $inventory = BranchInventory::where('branch_id', $branchId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()->first();
                if (! $inventory) {
                    throw ValidationException::withMessages(['items' => __('This product has no inventory record for the active branch.')]);
                }

                $qty = round((float) $line['quantity'], 3);
                if ($qty <= 0) {
                    continue;
                }

                if ($inventory->stock_quantity < $qty) {
                    if (! $allowOversell) {
                        throw ValidationException::withMessages([
                            'items' => __('Insufficient stock for :name (have :have, need :need).', [
                                'name' => $product->name,
                                'have' => $inventory->stock_quantity,
                                'need' => $qty,
                            ]),
                        ]);
                    }
                    // Offline sale: the goods already left the shop, so complete it and
                    // let stock go negative. Flag it so the owner can reconcile.
                    $oversold[] = $product->name;
                }

                $price = round((float) ($line['price'] ?? $product->sale_price), 2);
                $lineDiscount = round((float) ($line['discount'] ?? 0), 2);
                $lineBase = ($price * $qty) - $lineDiscount;
                $lineTax = round($lineBase * ((float) $product->tax_rate / 100), 2);

                $subtotal += $price * $qty;
                $lineDiscountTotal += $lineDiscount;
                $taxTotal += $lineTax;

                $preparedItems[] = [
                    'product' => $product,
                    'inventory' => $inventory,
                    'name' => $product->name,
                    'price' => $price,
                    'cost' => (float) $product->cost_price,
                    'quantity' => $qty,
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => round($lineBase + $lineTax, 2),
                ];
            }

            if (empty($preparedItems)) {
                throw ValidationException::withMessages(['items' => __('The cart is empty.')]);
            }

            $totalDiscount = $lineDiscountTotal + $orderDiscount;
            $total = round($subtotal - $totalDiscount + $taxTotal, 2);
            if ($total < 0) {
                $total = 0;
            }

            $paymentMethod = $payload['payment_method'] ?? 'cash';
            $paid = round((float) ($payload['paid'] ?? $total), 2);
            if ($paymentMethod !== 'cash') {
                $paid = $total; // non-cash is exact
            }
            if ($paid < $total) {
                throw ValidationException::withMessages(['paid' => __('Amount paid is less than the total due.')]);
            }
            $change = round($paid - $total, 2);

            $session = CashSession::where('user_id', $userId)->where('branch_id', $branchId)
                ->where('status', 'open')->latest('opened_at')->first();

            $isOffline = (bool) ($payload['is_offline'] ?? false);
            $offlineAt = ! empty($payload['offline_created_at'])
                ? \Illuminate\Support\Carbon::parse($payload['offline_created_at'])
                : null;

            $note = $payload['note'] ?? null;
            if (! empty($oversold)) {
                $flag = 'Offline oversell (stock reconcile): '.implode(', ', array_unique($oversold));
                $note = $note ? $note.' | '.$flag : $flag;
            }

            $sale = Sale::create([
                'branch_id' => $branchId,
                'invoice_no' => Sale::generateInvoiceNo(),
                'client_uuid' => $clientUuid,
                'customer_id' => $payload['customer_id'] ?? null,
                'user_id' => $userId,
                'cash_session_id' => $session?->id,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax' => round($taxTotal, 2),
                'total' => $total,
                'paid' => $paid,
                'change_due' => $change,
                'payment_method' => $paymentMethod,
                'status' => 'completed',
                'is_offline' => $isOffline,
                'offline_created_at' => $offlineAt,
                'note' => $note,
            ]);

            // Land the sale on the day it actually happened, not the sync time.
            if ($offlineAt) {
                $sale->created_at = $offlineAt;
                $sale->save();
            }

            foreach ($preparedItems as $item) {
                /** @var Product $product */
                $product = $item['product'];
                /** @var BranchInventory $inventory */
                $inventory = $item['inventory'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'cost' => $item['cost'],
                    'quantity' => $item['quantity'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'subtotal' => $item['subtotal'],
                ]);

                $inventory->decrement('stock_quantity', $item['quantity']);
            }

            // Loyalty points + spend tracking
            if ($sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $rate = (float) Setting::get('loyalty_earn_rate', 1);
                    $customer->increment('loyalty_points', (int) floor($total * $rate / 100));
                    $customer->increment('total_spent', $total);
                }
            }

            // Roll into cash session
            if ($session) {
                if ($paymentMethod === 'cash') {
                    $session->increment('cash_sales', $total);
                } elseif ($paymentMethod === 'card') {
                    $session->increment('card_sales', $total);
                } else {
                    $session->increment('other_sales', $total);
                }
                $session->increment('orders_count');
                $session->increment('expected_cash', $paymentMethod === 'cash' ? $total : 0);
            }

            ActivityLog::record('sale', "Completed sale {$sale->invoice_no} for ".money($total), $sale);

            return $sale->load('items', 'customer', 'cashier');
        });
    }

    /**
     * Refund an entire sale: restores stock and reverses loyalty/cash session.
     */
    public function refund(Sale $sale, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            if ($sale->status === 'refunded') {
                throw ValidationException::withMessages(['sale' => __('This sale has already been refunded.')]);
            }

            foreach ($sale->items as $item) {
                if ($item->product_id && $item->product) {
                    $inventory = BranchInventory::where('branch_id', $sale->branch_id)
                        ->where('product_id', $item->product_id)
                        ->lockForUpdate()->firstOrFail();
                    $before = (float) $inventory->stock_quantity;
                    $inventory->increment('stock_quantity', $item->quantity);
                    StockAdjustment::create([
                        'branch_id' => $sale->branch_id,
                        'product_id' => $item->product_id,
                        'user_id' => auth()->id() ?? $sale->user_id,
                        'type' => 'add',
                        'quantity' => $item->quantity,
                        'stock_before' => $before,
                        'stock_after' => $before + $item->quantity,
                        'reason' => 'refund',
                        'note' => "Refund of sale {$sale->invoice_no}",
                    ]);
                }
                $item->update(['refunded_qty' => $item->quantity]);
            }

            if ($sale->customer_id && $sale->customer) {
                $sale->customer->decrement('total_spent', min($sale->total, $sale->customer->total_spent));
            }

            if ($sale->cash_session_id && $sale->cashSession && $sale->cashSession->status === 'open') {
                $session = $sale->cashSession;
                if ($sale->payment_method === 'cash') {
                    $session->increment('cash_refunds', $sale->total);
                    $session->decrement('expected_cash', $sale->total);
                }
            }

            $sale->update(['status' => 'refunded', 'note' => trim(($sale->note ? $sale->note.' | ' : '').'Refunded: '.$reason)]);

            ActivityLog::record('refund', "Refunded sale {$sale->invoice_no}", $sale);

            return $sale;
        });
    }
}
