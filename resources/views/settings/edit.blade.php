@extends('layouts.app')
@section('title', 'Settings')
@section('crumb', 'Store configuration')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Settings') }}</h2><p>{{ __('Configure your store, receipts and currency') }}</p></div>
</div>

<form method="POST" action="{{ route('settings.update') }}">
    @csrf @method('PUT')
    <div class="grid grid-2" style="align-items:start">
        <div class="card card-pad">
            <h3 style="margin-bottom:16px">{{ __('Store Information') }}</h3>
            <div class="field"><label>{{ __('Store name *') }}</label><input class="input" name="shop_name" value="{{ setting('shop_name') }}" required></div>
            <div class="field"><label>{{ __('Address') }}</label><input class="input" name="shop_address" value="{{ setting('shop_address') }}"></div>
            <div class="form-row">
                <div class="field"><label>{{ __('Phone') }}</label><input class="input" name="shop_phone" value="{{ setting('shop_phone') }}"></div>
                <div class="field"><label>{{ __('Email') }}</label><input class="input" type="email" name="shop_email" value="{{ setting('shop_email') }}"></div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:18px">
            <div class="card card-pad">
                <h3 style="margin-bottom:16px">{{ __('Currency & Tax') }}</h3>
                @php
                    $currencySymbol = setting('currency_symbol', '$');
                    $isPresetCurrency = in_array($currencySymbol, ['$', '؋'], true);
                @endphp
                <div class="form-row">
                    <div class="field">
                        <label for="currencyPreset">{{ __('Currency preset') }}</label>
                        <select class="select" id="currencyPreset">
                            <option value="$" @selected($currencySymbol === '$')>{{ __('US Dollar (USD)') }} · $</option>
                            <option value="؋" @selected($currencySymbol === '؋')>{{ __('Afghan Afghani (AFN)') }} · ؋</option>
                            <option value="custom" @selected(!$isPresetCurrency)>{{ __('Custom symbol') }}</option>
                        </select>
                    </div>
                    <div class="field"><label>{{ __('Symbol position') }}</label><select class="select" name="currency_position">
                        <option value="before" @selected(setting('currency_position')=='before')>{{ __('Symbol before amount') }}</option>
                        <option value="after" @selected(setting('currency_position')=='after')>{{ __('Symbol after amount') }}</option></select></div>
                </div>
                <div class="field">
                    <label for="currencySymbol">{{ __('Currency symbol *') }}</label>
                    <input class="input" id="currencySymbol" name="currency_symbol" value="{{ $currencySymbol }}" required maxlength="5">
                </div>
                <div class="hint" style="margin-top:-8px;margin-bottom:14px">{{ __('Choose a preset or select Custom symbol to enter your own. This changes the displayed currency symbol only; it does not convert stored amounts.') }}</div>
                <div class="field" style="margin:0"><label>{{ __('Default tax rate (%)') }}</label><input class="input t-mono" type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="{{ setting('default_tax_rate','0') }}"></div>
            </div>
            <div class="card card-pad">
                <h3 style="margin-bottom:16px">{{ __('Receipt & Loyalty') }}</h3>
                <div class="field"><label>{{ __('Receipt footer message') }}</label><textarea class="input" name="receipt_footer">{{ setting('receipt_footer') }}</textarea></div>
                <div class="field" style="margin:0"><label>{{ __('Loyalty points per 100 spent') }}</label><input class="input t-mono" type="number" step="0.01" min="0" name="loyalty_earn_rate" value="{{ setting('loyalty_earn_rate','1') }}">
                    <div class="hint">{{ __('Points awarded to customers for every :amount spent.', ['amount' => money(100)]) }}</div></div>
            </div>
        </div>
    </div>
    <div class="flex gap-2 mt-2" style="justify-content:flex-end">
        <button class="btn btn-primary">@include('partials.icon',['name'=>'check']) {{ __('Save Settings') }}</button>
    </div>
</form>

<script>
    (() => {
        const currencyPreset = document.getElementById('currencyPreset');
        const currencySymbol = document.getElementById('currencySymbol');

        function syncCurrencySymbol() {
            const isCustom = currencyPreset.value === 'custom';
            currencySymbol.readOnly = !isCustom;
            if (!isCustom) currencySymbol.value = currencyPreset.value;
        }

        currencyPreset.addEventListener('change', syncCurrencySymbol);
        currencySymbol.addEventListener('input', () => {
            if (currencySymbol.value !== currencyPreset.value) {
                currencyPreset.value = 'custom';
                syncCurrencySymbol();
            }
        });
        syncCurrencySymbol();
    })();
</script>
@endsection
