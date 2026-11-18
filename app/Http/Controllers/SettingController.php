<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:100'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'shop_phone' => ['nullable', 'string', 'max:40'],
            'shop_email' => ['nullable', 'email', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'currency_position' => ['required', 'in:before,after'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'loyalty_earn_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        ActivityLog::record('settings', 'Updated shop settings');

        return back()->with('success', __('Settings saved.'));
    }
}
