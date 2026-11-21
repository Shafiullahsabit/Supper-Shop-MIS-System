<?php

namespace App\Services;

use App\Models\BranchInventory;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockTransferReceipt;
use App\Models\StockTransferReceiptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class StockTransferService
{
    public function create(
        User $user,
        int $sourceBranchId,
        int $destinationBranchId,
        array $items,
        ?string $notes
    ): StockTransfer {
        if ($sourceBranchId === $destinationBranchId) {
            throw ValidationException::withMessages([
                'destination_branch_id' => __('The destination must be different from the source branch.'),
            ]);
        }

        $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id);
        if ($productIds->count() !== $productIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => __('A product can only be listed once on a transfer.'),
            ]);
        }

        return DB::transaction(function () use ($user, $sourceBranchId, $destinationBranchId, $items, $notes, $productIds): StockTransfer {
            $inventories = BranchInventory::query()
                ->where('branch_id', $sourceBranchId)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $inventory = $inventories->get((int) $item['product_id']);
                $available = $this->quantityUnits($inventory?->stock_quantity ?? 0);
                $requested = $this->quantityUnits($item['quantity']);

                if ($requested > $available) {
                    throw ValidationException::withMessages([
                        'items' => __('Insufficient stock at the source branch for :product (available :available, requested :requested).', [
                            'product' => $inventory?->product?->name ?? __('selected product'),
                            'available' => $this->formatQuantity($available),
                            'requested' => $this->formatQuantity($requested),
                        ]),
                    ]);
                }
            }

            $transfer = StockTransfer::create([
                'reference' => 'TR-TEMP-'.Str::uuid(),
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
                'created_by' => $user->id,
                'status' => StockTransfer::STATUS_PENDING_APPROVAL,
                'notes' => $notes,
            ]);

            $transfer->items()->createMany(collect($items)->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'requested_quantity' => $item['quantity'],
            ])->all());

            $transfer->update([
                'reference' => 'TR-'.str_pad((string) $transfer->id, 8, '0', STR_PAD_LEFT),
            ]);

            return $transfer;
        });
    }

    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureStatus($transfer, [StockTransfer::STATUS_PENDING_APPROVAL]);

            $transfer->update([
                'status' => StockTransfer::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejection_reason' => null,
            ]);

            return $transfer;
        });
    }

    public function reject(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureStatus($transfer, [StockTransfer::STATUS_PENDING_APPROVAL]);

            $transfer->update([
                'status' => StockTransfer::STATUS_REJECTED,
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            return $transfer;
        });
    }

    public function dispatch(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureStatus($transfer, [StockTransfer::STATUS_APPROVED]);

            $items = $transfer->items()->orderBy('product_id')->lockForUpdate()->get();
            $inventories = BranchInventory::query()
                ->where('branch_id', $transfer->source_branch_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $inventory = $inventories->get($item->product_id);
                $available = $this->quantityUnits($inventory?->stock_quantity ?? 0);
                $requested = $this->quantityUnits($item->requested_quantity);

                if ($requested > $available) {
                    throw ValidationException::withMessages([
                        'transfer' => __('Insufficient stock at the source branch for :product (available :available, requested :requested).', [
                            'product' => $item->product->name,
                            'available' => $this->formatQuantity($available),
                            'requested' => $this->formatQuantity($requested),
                        ]),
                    ]);
                }
            }

            foreach ($items as $item) {
                $inventory = $inventories->get($item->product_id);
                $inventory->update([
                    'stock_quantity' => $this->formatQuantity(
                        $this->quantityUnits($inventory->stock_quantity) - $this->quantityUnits($item->requested_quantity)
                    ),
                ]);
                $item->update(['dispatched_quantity' => $item->requested_quantity]);
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_IN_TRANSIT,
                'dispatched_by' => $user->id,
                'dispatched_at' => now(),
            ]);

            return $transfer;
        });
    }

    public function receive(StockTransfer $transfer, User $user, array $lines, bool $closeTransfer, ?string $notes): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $lines, $closeTransfer, $notes): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureStatus($transfer, [
                StockTransfer::STATUS_IN_TRANSIT,
                StockTransfer::STATUS_PARTIALLY_RECEIVED,
            ]);

            $items = $transfer->items()->orderBy('product_id')->lockForUpdate()->get();
            $lineByItemId = collect($lines)->keyBy(fn (array $line) => (int) $line['transfer_item_id']);
            if ($lineByItemId->count() !== $items->count() || $items->contains(fn (StockTransferItem $item) => ! $lineByItemId->has($item->id))) {
                throw ValidationException::withMessages([
                    'items' => __('Submit a receiving quantity for every transfer item.'),
                ]);
            }

            $receiptPlan = [];
            $receivedThisTime = 0;
            $hasDiscrepancy = false;
            $allReceived = true;

            foreach ($items as $item) {
                $line = $lineByItemId->get($item->id);
                $receivedBefore = $this->quantityUnits($item->receiptItems()->sum('quantity_received'));
                $dispatched = $this->quantityUnits($item->dispatched_quantity);
                $remaining = $dispatched - $receivedBefore;
                $quantity = $this->quantityUnits($line['quantity_received']);

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.quantity_received" => __('Received quantity cannot exceed the remaining dispatched quantity.'),
                    ]);
                }

                $remainingAfter = $remaining - $quantity;
                $reason = trim((string) ($line['discrepancy_reason'] ?? ''));
                if ($closeTransfer && $remainingAfter > 0 && $reason === '') {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.discrepancy_reason" => __('Explain the shortage or damage before closing this transfer.'),
                    ]);
                }

                if ($remainingAfter > 0) {
                    $allReceived = false;
                }
                if ($remainingAfter > 0 && $closeTransfer) {
                    $hasDiscrepancy = true;
                }
                $receivedThisTime += $quantity;
                $receiptPlan[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'reason' => $closeTransfer && $remainingAfter > 0 ? $reason : null,
                ];
            }

            if ($receivedThisTime === 0 && ! $closeTransfer) {
                throw ValidationException::withMessages([
                    'items' => __('Enter a received quantity or close the transfer with a discrepancy reason.'),
                ]);
            }

            $isFinal = $closeTransfer || $allReceived;
            $receipt = $transfer->receipts()->create([
                'received_by' => $user->id,
                'is_final' => $isFinal,
                'notes' => $notes,
                'received_at' => now(),
            ]);

            foreach ($receiptPlan as $planned) {
                /** @var StockTransferItem $item */
                $item = $planned['item'];
                $quantity = $planned['quantity'];

                if ($quantity > 0 || $planned['reason'] !== null) {
                    $receipt->items()->create([
                        'stock_transfer_item_id' => $item->id,
                        'quantity_received' => $this->formatQuantity($quantity),
                        'discrepancy_reason' => $planned['reason'],
                    ]);
                }

                if ($quantity === 0) {
                    continue;
                }

                $inventory = BranchInventory::firstOrCreate(
                    [
                        'branch_id' => $transfer->destination_branch_id,
                        'product_id' => $item->product_id,
                    ],
                    ['stock_quantity' => 0, 'alert_quantity' => 5]
                );
                $inventory = BranchInventory::query()->lockForUpdate()->findOrFail($inventory->id);
                $inventory->update([
                    'stock_quantity' => $this->formatQuantity(
                        $this->quantityUnits($inventory->stock_quantity) + $quantity
                    ),
                ]);
            }

            $transfer->update([
                'status' => $isFinal
                    ? ($hasDiscrepancy ? StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY : StockTransfer::STATUS_RECEIVED)
                    : ($receivedThisTime > 0 ? StockTransfer::STATUS_PARTIALLY_RECEIVED : StockTransfer::STATUS_IN_TRANSIT),
                'completed_by' => $isFinal ? $user->id : null,
                'completed_at' => $isFinal ? now() : null,
            ]);

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureStatus($transfer, [
                StockTransfer::STATUS_PENDING_APPROVAL,
                StockTransfer::STATUS_APPROVED,
            ]);

            $transfer->update([
                'status' => StockTransfer::STATUS_CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            return $transfer;
        });
    }

    private function ensureStatus(StockTransfer $transfer, array $allowed): void
    {
        if (! in_array($transfer->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'transfer' => __('This action is not available for the transfer’s current status.'),
            ]);
        }
    }

    private function quantityUnits(mixed $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function formatQuantity(int $units): float
    {
        return round($units / 1000, 3);
    }
}
