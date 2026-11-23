<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class OperationalNotificationService
{
    public function lowStock(int $branchId, string $productName, float $stock, float $threshold): void
    {
        $branch = Branch::findOrFail($branchId);
        $users = $this->usersWithPermission('inventory.view')
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'Super Admin'));
            })
            ->get();

        foreach ($users as $user) {
            $url = $user->hasRole('Super Admin')
                ? route('reports.inventory', ['branch_id' => $branchId])
                : route('inventory.index', ['filter' => 'low']);

            $user->notify(new OperationalNotification([
                'event' => 'low_stock',
                'category' => 'inventory',
                'title' => __('Low stock alert'),
                'title_key' => 'Low stock alert',
                'message' => __(':product at :branch has :stock remaining (alert level: :threshold).', [
                    'product' => $productName,
                    'branch' => $branch->name,
                    'stock' => $this->formatQuantity($stock),
                    'threshold' => $this->formatQuantity($threshold),
                ]),
                'message_key' => ':product at :branch has :stock remaining (alert level: :threshold).',
                'message_params' => [
                    'product' => $productName,
                    'branch' => $branch->name,
                    'stock' => $this->formatQuantity($stock),
                    'threshold' => $this->formatQuantity($threshold),
                ],
                'url' => $url,
                'icon' => 'alert',
                'branch_id' => $branchId,
            ]));
        }
    }

    public function stockTransfer(StockTransfer $transfer, string $event): void
    {
        $transfer->loadMissing('sourceBranch', 'destinationBranch');
        $source = $transfer->sourceBranch;
        $destination = $transfer->destinationBranch;

        [$recipients, $title, $message, $branchId] = match ($event) {
            'requested' => [
                User::query()->where('is_active', true)
                    ->permission('inventory.transfers.approve')->get(),
                __('Stock transfer approval requested'),
                __(':reference is waiting for approval from :source to :destination.', [
                    'reference' => $transfer->reference,
                    'source' => $source->name,
                    'destination' => $destination->name,
                ]),
                $transfer->source_branch_id,
            ],
            'approved' => [
                $this->branchTransferUsers((int) $transfer->source_branch_id),
                __('Stock transfer approved'),
                __(':reference was approved and is ready to dispatch.', [
                    'reference' => $transfer->reference,
                ]),
                $transfer->source_branch_id,
            ],
            'rejected' => [
                User::query()->whereKey($transfer->created_by)->where('is_active', true)->get(),
                __('Stock transfer rejected'),
                __(':reference was rejected: :reason', [
                    'reference' => $transfer->reference,
                    'reason' => $transfer->rejection_reason,
                ]),
                $transfer->source_branch_id,
            ],
            'dispatched' => [
                $this->branchTransferUsers((int) $transfer->destination_branch_id),
                __('Stock transfer dispatched'),
                __(':reference is on the way from :source to :destination.', [
                    'reference' => $transfer->reference,
                    'source' => $source->name,
                    'destination' => $destination->name,
                ]),
                $transfer->destination_branch_id,
            ],
            'received' => [
                $this->branchTransferUsers((int) $transfer->source_branch_id),
                match ($transfer->status) {
                    StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY => __('Transfer received with discrepancy'),
                    StockTransfer::STATUS_PARTIALLY_RECEIVED => __('Partial stock transfer receipt'),
                    default => __('Stock transfer received'),
                },
                match ($transfer->status) {
                    StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY => __(':reference was received at :destination with a discrepancy. Review the transfer details.', [
                        'reference' => $transfer->reference,
                        'destination' => $destination->name,
                    ]),
                    StockTransfer::STATUS_PARTIALLY_RECEIVED => __(':reference was partially received at :destination.', [
                        'reference' => $transfer->reference,
                        'destination' => $destination->name,
                    ]),
                    default => __(':reference was received at :destination.', [
                        'reference' => $transfer->reference,
                        'destination' => $destination->name,
                    ]),
                },
                $transfer->source_branch_id,
            ],
            'cancelled' => [
                $this->branchTransferUsers((int) $transfer->destination_branch_id),
                __('Stock transfer cancelled'),
                __(':reference was cancelled by the source branch.', [
                    'reference' => $transfer->reference,
                ]),
                $transfer->destination_branch_id,
            ],
            default => throw new InvalidArgumentException('Unsupported stock transfer notification event.'),
        };

        foreach ($recipients as $recipient) {
            $recipient->notify(new OperationalNotification([
                'event' => 'transfer_'.$event,
                'category' => 'transfers',
                'title' => $title,
                'title_key' => $this->transferTitleKey($transfer, $event),
                'message' => $message,
                'message_key' => $this->transferMessageKey($event, $transfer),
                'message_params' => $this->transferMessageParams($event, $transfer, $source, $destination),
                'url' => route('transfers.show', $transfer),
                'icon' => 'truck',
                'branch_id' => (int) $branchId,
                'transfer_id' => $transfer->id,
            ]));
        }
    }

    private function usersWithPermission(string $permission): Builder
    {
        return User::query()->where('is_active', true)->permission($permission);
    }

    private function branchTransferUsers(int $branchId): Collection
    {
        return $this->usersWithPermission('inventory.transfers')
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'Super Admin'));
            })
            ->get();
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private function transferTitleKey(StockTransfer $transfer, string $event): string
    {
        return match ($event) {
            'requested' => 'Stock transfer approval requested',
            'approved' => 'Stock transfer approved',
            'rejected' => 'Stock transfer rejected',
            'dispatched' => 'Stock transfer dispatched',
            'received' => match ($transfer->status) {
                StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY => 'Transfer received with discrepancy',
                StockTransfer::STATUS_PARTIALLY_RECEIVED => 'Partial stock transfer receipt',
                default => 'Stock transfer received',
            },
            'cancelled' => 'Stock transfer cancelled',
            default => throw new InvalidArgumentException('Unsupported stock transfer notification event.'),
        };
    }

    private function transferMessageKey(string $event, StockTransfer $transfer): string
    {
        return match ($event) {
            'requested' => ':reference is waiting for approval from :source to :destination.',
            'approved' => ':reference was approved and is ready to dispatch.',
            'rejected' => ':reference was rejected: :reason',
            'dispatched' => ':reference is on the way from :source to :destination.',
            'received' => match ($transfer->status) {
                StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY => ':reference was received at :destination with a discrepancy. Review the transfer details.',
                StockTransfer::STATUS_PARTIALLY_RECEIVED => ':reference was partially received at :destination.',
                default => ':reference was received at :destination.',
            },
            'cancelled' => ':reference was cancelled by the source branch.',
            default => throw new InvalidArgumentException('Unsupported stock transfer notification event.'),
        };
    }

    private function transferMessageParams(
        string $event,
        StockTransfer $transfer,
        Branch $source,
        Branch $destination
    ): array {
        return match ($event) {
            'requested', 'dispatched' => [
                'reference' => $transfer->reference,
                'source' => $source->name,
                'destination' => $destination->name,
            ],
            'approved' => ['reference' => $transfer->reference],
            'rejected' => [
                'reference' => $transfer->reference,
                'reason' => $transfer->rejection_reason,
            ],
            'received' => [
                'reference' => $transfer->reference,
                'destination' => $destination->name,
            ],
            'cancelled' => ['reference' => $transfer->reference],
            default => throw new InvalidArgumentException('Unsupported stock transfer notification event.'),
        };
    }
}
