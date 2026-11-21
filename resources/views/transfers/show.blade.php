@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('crumb', 'Stock Transfers')

@section('content')
@php
    $statusClass = match ($transfer->status) {
        'pending_approval' => 'badge-amber',
        'approved', 'in_transit', 'partially_received' => 'badge-blue',
        'received' => 'badge-green',
        'received_with_discrepancy', 'rejected' => 'badge-red',
        default => 'badge-gray',
    };
@endphp

<div class="page-head">
    <div class="titles">
        <div class="transfer-title-line">
            <h2>{{ $transfer->reference }}</h2>
            <span class="badge {{ $statusClass }}">{{ __($transfer->status) }}</span>
        </div>
        <p>{{ __('Created :date by :user', ['date' => $transfer->created_at->translatedFormat('d M Y, h:i A'), 'user' => $transfer->creator?->name ?? '—']) }}</p>
    </div>
    <div class="actions">
        <a href="{{ route('transfers.index') }}" class="btn btn-light">{{ __('Back to Transfers') }}</a>
        @if($canApprove)
            <form method="POST" action="{{ route('transfers.approve', $transfer) }}" onsubmit="return SS.confirmSubmit(this, '{{ __('Approve this transfer request?') }}')">
                @csrf
                <button class="btn btn-primary">@include('partials.icon', ['name' => 'check']) {{ __('Approve') }}</button>
            </form>
        @endif
        @if($canDispatch)
            <form method="POST" action="{{ route('transfers.dispatch', $transfer) }}" onsubmit="return SS.confirmSubmit(this, '{{ __('Dispatching deducts the items from the source branch stock. Continue?') }}')">
                @csrf
                <button class="btn btn-primary">@include('partials.icon', ['name' => 'truck']) {{ __('Dispatch') }}</button>
            </form>
        @endif
        @if($canReceive)
            <a href="{{ route('transfers.receive.form', $transfer) }}" class="btn btn-success">@include('partials.icon', ['name' => 'inbox']) {{ __('Record Receipt') }}</a>
        @endif
        @if($canCancel)
            <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" onsubmit="return SS.confirmSubmit(this, '{{ __('Cancel this transfer? No stock has been dispatched yet.') }}')">
                @csrf
                <button class="btn btn-ghost-danger">{{ __('Cancel Request') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="transfer-route card card-pad mb-2">
    <div>
        <span>{{ __('From branch') }}</span>
        <strong>{{ $transfer->sourceBranch->branch_number }} · {{ $transfer->sourceBranch->name }}</strong>
        <small>{{ __('Source') }}</small>
    </div>
    <div class="transfer-route-arrow">@include('partials.icon', ['name' => 'arrow-right'])</div>
    <div>
        <span>{{ __('To branch') }}</span>
        <strong>{{ $transfer->destinationBranch->branch_number }} · {{ $transfer->destinationBranch->name }}</strong>
        <small>{{ __('Destination') }}</small>
    </div>
</div>

<div class="grid grid-2 transfer-summary-grid mb-2">
    <div class="card transfer-summary">
        <div class="profile-section-icon">@include('partials.icon', ['name' => 'user'])</div>
        <div><span>{{ __('Requested by') }}</span><strong>{{ $transfer->creator?->name ?? '—' }}</strong></div>
    </div>
    <div class="card transfer-summary">
        <div class="profile-section-icon profile-section-icon-secure">@include('partials.icon', ['name' => 'shield'])</div>
        <div><span>{{ __('Approval') }}</span><strong>{{ $transfer->approver?->name ?? ($transfer->status === 'pending_approval' ? __('Awaiting Approval') : '—') }}</strong></div>
    </div>
    <div class="card transfer-summary">
        <div class="profile-section-icon">@include('partials.icon', ['name' => 'truck'])</div>
        <div><span>{{ __('Dispatched') }}</span><strong>{{ $transfer->dispatched_at?->translatedFormat('d M Y, h:i A') ?? '—' }}</strong></div>
    </div>
    <div class="card transfer-summary">
        <div class="profile-section-icon profile-section-icon-secure">@include('partials.icon', ['name' => 'check-circle'])</div>
        <div><span>{{ __('Completed') }}</span><strong>{{ $transfer->completed_at?->translatedFormat('d M Y, h:i A') ?? '—' }}</strong></div>
    </div>
</div>

@if($transfer->notes)
    <div class="alert alert-info mb-2">@include('partials.icon', ['name' => 'info']) <span><strong>{{ __('Request notes') }}:</strong> {{ $transfer->notes }}</span></div>
@endif
@if($transfer->rejection_reason)
    <div class="alert alert-error mb-2">@include('partials.icon', ['name' => 'alert']) <span><strong>{{ __('Rejection reason') }}:</strong> {{ $transfer->rejection_reason }}</span></div>
@endif

@if($canReject)
    <form method="POST" action="{{ route('transfers.reject', $transfer) }}" class="card transfer-reject-card mb-2" onsubmit="return SS.confirmSubmit(this, '{{ __('Reject this transfer request?') }}')">
        @csrf
        <div class="field" style="margin:0">
            <label for="rejectionReason">{{ __('Reject request (reason required)') }}</label>
            <textarea class="input" id="rejectionReason" name="rejection_reason" rows="2" maxlength="1000" required>{{ old('rejection_reason') }}</textarea>
        </div>
        <button class="btn btn-ghost-danger">{{ __('Reject Transfer') }}</button>
    </form>
@endif

<section class="card transfer-table-card">
    <div class="card-head"><div><h3>{{ __('Transfer Items') }}</h3><div class="sub">{{ __('Dispatch reduces source stock; each receipt adds only the quantity actually received.') }}</div></div></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th class="t-right">{{ __('Requested') }}</th>
                    <th class="t-right">{{ __('Dispatched') }}</th>
                    <th class="t-right">{{ __('Received') }}</th>
                    <th class="t-right">{{ __('Remaining in transit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfer->items as $item)
                    @php
                        $received = (float) $item->receiptItems->sum('quantity_received');
                        $dispatched = (float) ($item->dispatched_quantity ?? 0);
                        $remaining = max(0, $dispatched - $received);
                    @endphp
                    <tr>
                        <td><div class="t-title">{{ $item->product->name }}</div><div class="t-sub t-mono">{{ $item->product->sku }}</div></td>
                        <td class="t-right t-mono">{{ number_format((float) $item->requested_quantity, 3) }} {{ $item->product->unit?->short_name }}</td>
                        <td class="t-right t-mono">{{ $dispatched ? number_format($dispatched, 3) : '—' }}</td>
                        <td class="t-right t-mono fw-700">{{ number_format($received, 3) }}</td>
                        <td class="t-right t-mono">{{ $transfer->dispatched_at ? number_format($remaining, 3) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if($transfer->receipts->isNotEmpty())
    <section class="card transfer-history-card mt-2">
        <div class="card-head"><div><h3>{{ __('Receipt History') }}</h3><div class="sub">{{ __('Partial receipts and discrepancies are recorded for audit.') }}</div></div></div>
        <div class="transfer-history">
            @foreach($transfer->receipts as $receipt)
                <article class="transfer-receipt">
                    <div class="transfer-receipt-head">
                        <strong>{{ $receipt->received_at->translatedFormat('d M Y, h:i A') }}</strong>
                        <span>{{ __('Received by :user', ['user' => $receipt->receiver?->name ?? '—']) }}</span>
                        @if($receipt->is_final)<span class="badge {{ $transfer->status === 'received_with_discrepancy' ? 'badge-red' : 'badge-green' }}">{{ $transfer->status === 'received_with_discrepancy' ? __('Final receipt with discrepancy') : __('Final receipt') }}</span>@else<span class="badge badge-blue">{{ __('Partial receipt') }}</span>@endif
                    </div>
                    @if($receipt->items->isNotEmpty())
                        <ul>
                            @foreach($receipt->items as $receiptItem)
                                <li>
                                    {{ $receiptItem->transferItem->product->name }}:
                                    <strong>{{ number_format((float) $receiptItem->quantity_received, 3) }}</strong>
                                    @if($receiptItem->discrepancy_reason)
                                        <span class="text-red">{{ __('Unreceived quantity closed') }} — {{ $receiptItem->discrepancy_reason }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($receipt->notes)<p>{{ $receipt->notes }}</p>@endif
                </article>
            @endforeach
        </div>
    </section>
@endif
@endsection
