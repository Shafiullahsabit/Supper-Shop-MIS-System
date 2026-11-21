@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('crumb', 'Stock Transfers')

@section('content')
<div class="page-head">
    <div class="titles">
        <h2>{{ __('Receive :reference', ['reference' => $transfer->reference]) }}</h2>
        <p>{{ $transfer->sourceBranch->name }} → {{ $transfer->destinationBranch->name }}</p>
    </div>
    <div class="actions"><a href="{{ route('transfers.show', $transfer) }}" class="btn btn-light">{{ __('Back to Transfer') }}</a></div>
</div>

<div class="alert alert-info mb-2">
    @include('partials.icon', ['name' => 'info'])
    <span>{{ __('Enter the quantities arriving in this delivery. You can record several partial receipts. To finish the transfer, select Close transfer; any quantity still missing then needs a reason.') }}</span>
</div>

<form method="POST" action="{{ route('transfers.receive', $transfer) }}" id="receiveTransferForm">
    @csrf
    <section class="card transfer-table-card">
        <div class="card-head"><div><h3>{{ __('Items in this delivery') }}</h3><div class="sub">{{ __('Received stock is added to this branch only.') }}</div></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>{{ __('Product') }}</th><th class="t-right">{{ __('Dispatched') }}</th><th class="t-right">{{ __('Previously received') }}</th><th class="t-right">{{ __('Still expected') }}</th><th>{{ __('Received now') }}</th><th>{{ __('Shortage / damage reason') }}</th></tr></thead>
                <tbody>
                    @foreach($transfer->items as $item)
                        @php
                            $received = (float) $item->receiptItems->sum('quantity_received');
                            $remaining = max(0, (float) $item->dispatched_quantity - $received);
                        @endphp
                        <tr class="receive-line" data-remaining="{{ $remaining }}">
                            <td><div class="t-title">{{ $item->product->name }}</div><div class="t-sub t-mono">{{ $item->product->sku }} · {{ $item->product->unit?->short_name }}</div></td>
                            <td class="t-right t-mono">{{ number_format((float) $item->dispatched_quantity, 3) }}</td>
                            <td class="t-right t-mono">{{ number_format($received, 3) }}</td>
                            <td class="t-right t-mono fw-700">{{ number_format($remaining, 3) }}</td>
                            <td>
                                <input type="hidden" name="items[{{ $item->id }}][transfer_item_id]" value="{{ $item->id }}">
                                <input class="input t-mono receive-quantity" type="number" name="items[{{ $item->id }}][quantity_received]" value="{{ old("items.{$item->id}.quantity_received", 0) }}" min="0" max="{{ $remaining }}" step="0.001" required>
                                @error("items.{$item->id}.quantity_received")<div class="field-error">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input class="input discrepancy-reason" type="text" name="items[{{ $item->id }}][discrepancy_reason]" value="{{ old("items.{$item->id}.discrepancy_reason") }}" maxlength="1000" placeholder="{{ __('Required only when closing with a shortage') }}">
                                @error("items.{$item->id}.discrepancy_reason")<div class="field-error">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="transfer-receive-options">
            <label class="transfer-close-option">
                <input type="checkbox" name="close_transfer" value="1" id="closeTransfer" @checked(old('close_transfer'))>
                <span><strong>{{ __('Close transfer after this receipt') }}</strong><small>{{ __('Any quantity not received will be closed as a discrepancy.') }}</small></span>
            </label>
            <div class="field">
                <label for="receiptNotes">{{ __('Receipt notes (optional)') }}</label>
                <textarea class="input" id="receiptNotes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="transfer-form-footer">
            <p>@include('partials.icon', ['name' => 'shield']) {{ __('Only the quantity entered as received will be added to destination stock.') }}</p>
            <button class="btn btn-success">@include('partials.icon', ['name' => 'check']) {{ __('Record Receipt') }}</button>
        </div>
    </section>
</form>

@push('scripts')
<script>
    (() => {
        const closeTransfer = document.getElementById('closeTransfer');
        const lines = document.querySelectorAll('.receive-line');

        function syncReasons() {
            lines.forEach((line) => {
                const quantity = Number(line.querySelector('.receive-quantity').value || 0);
                const remaining = Number(line.dataset.remaining || 0);
                const reason = line.querySelector('.discrepancy-reason');
                reason.required = closeTransfer.checked && quantity < remaining;
            });
        }

        closeTransfer.addEventListener('change', syncReasons);
        document.querySelectorAll('.receive-quantity').forEach((input) => input.addEventListener('input', syncReasons));
        syncReasons();
    })();
</script>
@endpush
@endsection
