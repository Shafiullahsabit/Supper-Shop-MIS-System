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
                <div class="form-row">
                    <div class="field"><label>{{ __('Currency symbol *') }}</label><input class="input" name="currency_symbol" value="{{ setting('currency_symbol','$') }}" required></div>
                    <div class="field"><label>{{ __('Symbol position') }}</label><select class="select" name="currency_position">
                        <option value="before" @selected(setting('currency_position')=='before')>{{ __('Before ($100)') }}</option>
                        <option value="after" @selected(setting('currency_position')=='after')>{{ __('After (100$)') }}</option></select></div>
                </div>
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
@endsection
