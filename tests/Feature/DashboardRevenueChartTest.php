<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DashboardRevenueChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_keeps_the_bar_chart_and_builds_daily_and_weekly_series(): void
    {
        Carbon::setLocale('en');
        app()->setLocale('en');
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));

        $cashier = User::factory()->create();
        $this->actingAs($cashier);

        $this->createSale($cashier, 'INV-CURRENT-1', 25, '2026-11-16 10:00:00');
        $this->createSale($cashier, 'INV-CURRENT-2', 10, '2026-11-17 10:00:00');
        $this->createSale($cashier, 'INV-REFUNDED', 200, '2026-11-17 11:00:00', 'refunded');
        $this->createSale($cashier, 'INV-PREVIOUS', 8, '2026-11-09 10:00:00');

        $dashboard = app(DashboardController::class)->index();
        $data = $dashboard->getData();
        $weekly = $data['weeklyChart']->keyBy('key');

        $this->assertCount(7, $data['chart']);
        $this->assertCount(8, $data['weeklyChart']);
        $this->assertSame(35.0, (float) $weekly['2026-11-16']['value']);
        $this->assertSame(8.0, (float) $weekly['2026-11-09']['value']);
        $this->assertSame(35.0, (float) $data['chart']->sum('value'));

        $html = $dashboard->with('errors', new ViewErrorBag())->render();
        $this->assertStringContainsString('class="bars"', $html);
        $this->assertStringContainsString('data-chart-mode="line"', $html);
        $this->assertStringContainsString('data-chart-period="daily"', $html);
        $this->assertStringContainsString('data-chart-period="weekly"', $html);
    }

    private function createSale(User $cashier, string $invoice, float $total, string $createdAt, string $status = 'completed'): void
    {
        $sale = Sale::create([
            'invoice_no' => $invoice,
            'user_id' => $cashier->id,
            'subtotal' => $total,
            'total' => $total,
            'paid' => $total,
            'status' => $status,
        ]);
        $sale->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->save();
    }
}
