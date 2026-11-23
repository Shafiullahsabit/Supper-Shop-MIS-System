<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CurrencySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_afghani_preset_uses_the_afghani_symbol_in_currency_formatting(): void
    {
        Setting::put('currency_symbol', '؋');
        Setting::put('currency_position', 'after');

        $html = $this->renderSettings();

        $this->assertStringContainsString('Afghan Afghani (AFN)', $html);
        $this->assertStringContainsString('value="؋" selected', $html);
        $this->assertStringContainsString('name="currency_symbol" value="؋"', $html);
        $this->assertSame('123.45؋', money(123.45));
    }

    public function test_custom_currency_symbols_remain_available(): void
    {
        Setting::put('currency_symbol', '€');

        $html = $this->renderSettings();

        $this->assertStringContainsString('value="custom" selected', $html);
        $this->assertStringContainsString('name="currency_symbol" value="€"', $html);
    }

    public function test_expense_currency_formatting_is_independent_of_the_shop_currency(): void
    {
        Setting::put('currency_symbol', '€');

        $this->assertSame('؋123.45', money(123.45, 'AFN'));
        $this->assertSame('$123.45', money(123.45, 'USD'));
        $this->assertSame('€123.45', money(123.45));
    }

    private function renderSettings(): string
    {
        return view('settings.edit')
            ->with('errors', new ViewErrorBag())
            ->render();
    }
}
