<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('money')) {
    /**
     * Format a numeric value as currency using shop settings.
     */
    function money($amount): string
    {
        $symbol = Setting::get('currency_symbol', '$');
        $position = Setting::get('currency_position', 'before');
        $formatted = number_format((float) $amount, 2);

        return $position === 'after' ? "{$formatted}{$symbol}" : "{$symbol}{$formatted}";
    }
}
