@extends('layouts.app')
@section('title', 'New Transfer Request')
@section('crumb', 'Stock Transfers')

@section('content')
@php
    $oldItems = old('items');
    if (! is_array($oldItems) || $oldItems === []) {
        $oldItems = [['product_id' => '', 'quantity' => '']];
    }
@endphp
<div class="page-head">
    <div class="titles">
        <h2>{{ __('New Transfer Request') }}</h2>
        <p>{{ __('Choose products to move from the active branch. A Super Admin must approve the request before dispatch.') }}</p>
    </div>
    <div class="actions"><a href="{{ route('transfers.index') }}" class="btn btn-light">{{ __('Back to Transfers') }}</a></div>
</div>

<div class="transfer-route card card-pad mb-2">
    <div>
        <span>{{ __('From branch') }}</span>
        <strong>{{ $sourceBranch->branch_number }} · {{ $sourceBranch->name }}</strong>
        <small>{{ __('Active source branch') }}</small>
    </div>
    <div class="transfer-route-arrow">@include('partials.icon', ['name' => 'arrow-right'])</div>
    <div class="field" style="margin:0;min-width:min(100%,320px)">
        <label for="destinationBranch">{{ __('To branch') }}</label>
        <select id="destinationBranch" name="destination_branch_id" form="transferCreateForm" class="select" required>
            <option value="">{{ __('Select destination branch') }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('destination_branch_id') == $branch->id)>{{ $branch->branch_number }} · {{ $branch->name }}</option>
            @endforeach
        </select>
        @error('destination_branch_id')<div class="field-error">{{ $message }}</div>@enderror
    </div>
</div>

<form method="POST" action="{{ route('transfers.store') }}" id="transferCreateForm">
    @csrf
    <div class="card transfer-form-card">
        <div class="card-head">
            <div>
                <h3>{{ __('Products to transfer') }}</h3>
                <div class="sub">{{ __('The source stock is checked again when the transfer is dispatched.') }}</div>
            </div>
            <button type="button" class="btn btn-light btn-sm" id="addTransferItem">@include('partials.icon', ['name' => 'plus']) {{ __('Add product') }}</button>
        </div>
        <div class="transfer-lines" id="transferLines">
            @foreach($oldItems as $index => $oldItem)
                @php $oldItem = is_array($oldItem) ? $oldItem : []; @endphp
                <div class="transfer-line">
                    <div class="field">
                        <label>{{ __('Product') }}</label>
                        <select class="select" name="items[{{ $index }}][product_id]" data-product-select required>
                            <option value="" data-stock="0">{{ __('Select a product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit?->short_name }}" @selected((int) ($oldItem['product_id'] ?? 0) === $product->id)>
                                    {{ $product->name }} · {{ $product->sku }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>{{ __('Quantity to send') }}</label>
                        <input class="input t-mono" type="number" name="items[{{ $index }}][quantity]" value="{{ $oldItem['quantity'] ?? '' }}" min="0.001" step="0.001" data-quantity-input required>
                    </div>
                    <div class="transfer-available">
                        <span>{{ __('Available') }}</span>
                        <strong data-available-stock>0</strong>
                        <small data-available-unit></small>
                    </div>
                    <button type="button" class="icon-btn danger" data-remove-transfer-item aria-label="{{ __('Remove product') }}" title="{{ __('Remove product') }}">
                        @include('partials.icon', ['name' => 'trash'])
                    </button>
                </div>
            @endforeach
        </div>
        @error('items')<div class="field-error transfer-error">{{ $message }}</div>@enderror
        <div class="field transfer-notes">
            <label for="transferNotes">{{ __('Request notes (optional)') }}</label>
            <textarea class="input" id="transferNotes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
        </div>
        <div class="transfer-form-footer">
            <p>@include('partials.icon', ['name' => 'shield']) {{ __('After submission, the request will wait for Super Admin approval.') }}</p>
            <button class="btn btn-primary" @disabled($branches->isEmpty() || $products->isEmpty())>
                @include('partials.icon', ['name' => 'send']) {{ __('Submit for Approval') }}
            </button>
        </div>
    </div>
</form>

<template id="transferItemTemplate">
    <div class="transfer-line">
        <div class="field">
            <label>{{ __('Product') }}</label>
            <select class="select" name="items[__INDEX__][product_id]" data-product-select required>
                <option value="" data-stock="0">{{ __('Select a product') }}</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit?->short_name }}">
                        {{ $product->name }} · {{ $product->sku }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>{{ __('Quantity to send') }}</label>
            <input class="input t-mono" type="number" name="items[__INDEX__][quantity]" min="0.001" step="0.001" data-quantity-input required>
        </div>
        <div class="transfer-available">
            <span>{{ __('Available') }}</span>
            <strong data-available-stock>0</strong>
            <small data-available-unit></small>
        </div>
        <button type="button" class="icon-btn danger" data-remove-transfer-item aria-label="{{ __('Remove product') }}" title="{{ __('Remove product') }}">
            @include('partials.icon', ['name' => 'trash'])
        </button>
    </div>
</template>

@push('scripts')
<script>
    (() => {
        const lines = document.getElementById('transferLines');
        const template = document.getElementById('transferItemTemplate');
        let nextIndex = {{ count($oldItems) }};

        function updateLine(line) {
            const select = line.querySelector('[data-product-select]');
            const option = select.options[select.selectedIndex];
            const stock = Number(option.dataset.stock || 0);
            line.querySelector('[data-available-stock]').textContent = stock;
            line.querySelector('[data-available-unit]').textContent = option.dataset.unit || '';
            const quantity = line.querySelector('[data-quantity-input]');
            if (select.value) quantity.max = stock;
            else quantity.removeAttribute('max');
        }

        lines.querySelectorAll('.transfer-line').forEach((line) => {
            updateLine(line);
            line.querySelector('[data-product-select]').addEventListener('change', () => updateLine(line));
        });

        document.getElementById('addTransferItem').addEventListener('click', () => {
            const markup = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
            lines.insertAdjacentHTML('beforeend', markup);
            const line = lines.lastElementChild;
            line.querySelector('[data-product-select]').addEventListener('change', () => updateLine(line));
            updateLine(line);
            line.querySelector('[data-product-select]').focus();
        });

        lines.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-transfer-item]');
            if (removeButton && lines.querySelectorAll('.transfer-line').length > 1) {
                removeButton.closest('.transfer-line').remove();
            }
        });
    })();
</script>
@endpush
@endsection
