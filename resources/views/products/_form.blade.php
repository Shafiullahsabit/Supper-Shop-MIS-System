@php $p = $product ?? null; @endphp
<div class="grid" style="grid-template-columns: 2fr 1fr; align-items:start">
    <div class="card card-pad">
        <h3 style="margin-bottom:16px">{{ __('Product details') }}</h3>
        <div class="field">
            <label>{{ __('Product name *') }}</label>
            <input class="input" name="name" value="{{ old('name', $p->name ?? '') }}" placeholder="{{ __('e.g. Basmati Rice 5kg') }}" required>
            @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <div class="field">
                <label>{{ __('SKU') }}</label>
                <input class="input t-mono" name="sku" value="{{ old('sku', $p->sku ?? '') }}" placeholder="{{ __('Auto-generated if blank') }}">
                @error('sku')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label>{{ __('Barcode') }}</label>
                <input class="input t-mono" name="barcode" value="{{ old('barcode', $p->barcode ?? '') }}" placeholder="{{ __('Scan or type barcode') }}">
                @error('barcode')<div class="err">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-row-3">
            <div class="field">
                <label>{{ __('Category') }}</label>
                <select class="select" name="category_id">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id',$p->category_id ?? '')==$c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ __('Unit') }}</label>
                <select class="select" name="unit_id">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id',$p->unit_id ?? '')==$u->id)>{{ $u->name }} ({{ $u->short_name }})</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ __('Supplier') }}</label>
                <select class="select" name="supplier_id">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id',$p->supplier_id ?? '')==$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label>{{ __('Description') }}</label>
            <textarea class="input" name="description" placeholder="{{ __('Optional notes about this product') }}">{{ old('description', $p->description ?? '') }}</textarea>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card card-pad">
            <h3 style="margin-bottom:16px">{{ __('Pricing') }}</h3>
            <div class="field">
                <label>{{ __('Cost price *') }}</label>
                <input class="input t-mono" type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $p->cost_price ?? '0.00') }}" required>
                @error('cost_price')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label>{{ __('Sale price *') }}</label>
                <input class="input t-mono" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $p->sale_price ?? '0.00') }}" required>
                @error('sale_price')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field" style="margin:0">
                <label>{{ __('Tax rate (%)') }}</label>
                <input class="input t-mono" type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $p->tax_rate ?? '0') }}">
            </div>
        </div>
        <div class="card card-pad">
            <h3 style="margin-bottom:16px">{{ __('Inventory') }}</h3>
            <div class="field">
                <label>{{ __('Stock quantity *') }}</label>
                <input class="input t-mono" type="number" step="0.001" min="0" name="stock_quantity" value="{{ old('stock_quantity', $p->stock_quantity ?? '0') }}" required>
            </div>
            <div class="field">
                <label>{{ __('Low-stock alert at') }}</label>
                <input class="input t-mono" type="number" step="0.001" min="0" name="alert_quantity" value="{{ old('alert_quantity', $p->alert_quantity ?? '5') }}">
            </div>
            <label class="switch" style="margin-top:6px">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p->is_active ?? true))>
                <span class="track"></span>
                <span style="font-weight:600;font-size:13px;color:var(--ink-soft)">{{ __('Active (available in POS)') }}</span>
            </label>
        </div>
    </div>
</div>

<div class="flex gap-2 mt-2" style="justify-content:flex-end">
    <a href="{{ route('products.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    <button class="btn btn-primary">@include('partials.icon',['name'=>'check']) {{ isset($product) ? __('Update Product') : __('Save Product') }}</button>
</div>
