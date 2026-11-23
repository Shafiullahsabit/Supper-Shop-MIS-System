<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseAndReportExportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $mainBranch;

    private Branch $secondBranch;

    private User $mainManager;

    private User $secondManager;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->mainBranch = Branch::default();
        $this->secondBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
        $this->mainManager = $this->userInBranch($this->mainBranch, 'Manager', 'main-manager@example.test');
        $this->secondManager = $this->userInBranch($this->secondBranch, 'Manager', 'second-manager@example.test');
        $this->superAdmin = $this->userInBranch($this->mainBranch, 'Super Admin', 'super-admin@example.test');
    }

    public function test_expenses_track_payment_status_and_are_isolated_by_branch(): void
    {
        $category = ExpenseCategory::create(['name' => 'Utilities']);

        $this->actingAs($this->mainManager)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Main branch electricity',
            'amount' => 45.50,
            'currency' => 'AFN',
            'payment_method' => 'bank',
            'status' => 'unpaid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->mainManager)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Main branch water',
            'amount' => 12,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'status' => 'paid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $paidExpense = Expense::where('description', 'Main branch water')->firstOrFail();
        $this->assertSame(now()->toDateString(), $paidExpense->paid_at->format('Y-m-d'));

        $this->actingAs($this->secondManager)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertDontSee('Main branch electricity')
            ->assertDontSee('Main branch water');

        $this->actingAs($this->superAdmin)
            ->get(route('expenses.index', ['branch_id' => 'all']))
            ->assertOk()
            ->assertSee('Main branch electricity')
            ->assertSee('Main branch water');
    }

    public function test_supplier_partial_payments_are_logged_and_reduce_outstanding_balance(): void
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'opening_balance' => 10,
            'is_active' => true,
        ]);
        $purchase = Purchase::create([
            'branch_id' => $this->mainBranch->id,
            'reference' => 'PO-PAY-0001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->mainManager->id,
            'purchase_date' => now()->toDateString(),
            'subtotal' => 100,
            'total' => 100,
            'paid' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($this->mainManager)->post(route('purchases.payments.store', $purchase), [
            'amount' => 30,
            'payment_method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertRedirect(route('purchases.show', $purchase))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'amount' => 30,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'paid' => 30]);

        $this->actingAs($this->mainManager)->post(route('purchases.payments.store', $purchase), [
            'amount' => 71,
            'payment_method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $supplier = Supplier::withSum('purchases as purchases_total', 'total')
            ->withSum('purchases as purchases_paid', 'paid')
            ->findOrFail($supplier->id);
        $this->assertSame(80.0, $supplier->outstanding_balance);
    }

    public function test_exports_support_date_and_super_admin_branch_filters_and_excel_format(): void
    {
        $product = Product::create([
            'name' => 'Export Product',
            'sku' => 'EXPORT-001',
            'cost_price' => 4,
            'sale_price' => 8,
            'is_active' => true,
        ]);
        BranchInventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_id' => $product->id,
            'stock_quantity' => 7,
            'alert_quantity' => 3,
        ]);
        BranchInventory::create([
            'branch_id' => $this->secondBranch->id,
            'product_id' => $product->id,
            'stock_quantity' => 20,
            'alert_quantity' => 4,
        ]);
        $this->createSale($this->mainBranch, $this->mainManager, 'INV-EXPORT-001', $product);
        $this->createSale($this->secondBranch, $this->secondManager, 'INV-EXPORT-002', $product);
        $supplier = Supplier::create(['name' => 'Export Supplier', 'is_active' => true]);
        $purchase = Purchase::create([
            'branch_id' => $this->mainBranch->id,
            'reference' => 'PO-EXPORT-0001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->mainManager->id,
            'purchase_date' => now()->toDateString(),
            'subtotal' => 20,
            'total' => 20,
            'paid' => 5,
            'status' => 'received',
        ]);
        Expense::create([
            'branch_id' => $this->mainBranch->id,
            'user_id' => $this->mainManager->id,
            'expense_date' => now()->toDateString(),
            'description' => '=1+1',
            'amount' => 2,
            'currency' => 'AFN',
            'payment_method' => 'cash',
            'status' => 'unpaid',
        ]);

        $filters = [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $this->mainBranch->id,
        ];

        $salesCsv = $this->actingAs($this->superAdmin)
            ->get(route('reports.sales.export', ['format' => 'csv'] + $filters))
            ->assertDownload('sales-report.csv')
            ->streamedContent();
        $this->assertStringContainsString('INV-EXPORT-001', $salesCsv);
        $this->assertStringNotContainsString('INV-EXPORT-002', $salesCsv);

        $allBranchesSalesCsv = $this->get(route('reports.sales.export', [
            'format' => 'csv',
            'from' => $filters['from'],
            'to' => $filters['to'],
            'branch_id' => 'all',
        ]))->streamedContent();
        $this->assertStringContainsString('INV-EXPORT-001', $allBranchesSalesCsv);
        $this->assertStringContainsString('INV-EXPORT-002', $allBranchesSalesCsv);

        $profitCsv = $this->get(route('reports.profit.export', ['format' => 'csv'] + $filters))
            ->assertDownload('profit-report.csv')
            ->streamedContent();
        $this->assertStringContainsString('Export Product', $profitCsv);

        $purchaseCsv = $this->get(route('reports.purchases.export', ['format' => 'csv'] + $filters))
            ->assertDownload('purchases-report.csv')
            ->streamedContent();
        $this->assertStringContainsString('PO-EXPORT-0001', $purchaseCsv);

        $expenseCsv = $this->get(route('expenses.export', ['format' => 'csv'] + $filters))
            ->assertDownload('expenses-report.csv')
            ->streamedContent();
        $this->assertStringContainsString("'=1+1", $expenseCsv);
        $this->assertStringContainsString('Currency', $expenseCsv);
        $this->assertStringContainsString('AFN', $expenseCsv);

        $inventoryExcel = $this->get(route('reports.inventory.export', ['format' => 'xls'] + $filters))
            ->assertDownload('inventory-current.xls')
            ->streamedContent();
        $this->assertStringContainsString('<Workbook', $inventoryExcel);
        $this->assertStringNotContainsString('North Store', $inventoryExcel);

        $this->get(route('reports.sales'))->assertOk();
        $this->get(route('reports.profit'))->assertOk();
        $this->get(route('reports.top'))->assertOk();
        $this->get(route('reports.inventory'))->assertOk();
        $this->get(route('purchases.show', $purchase))->assertOk()->assertSee('Payment History');
        $this->get(route('suppliers.index'))->assertOk()->assertSee('Outstanding Balance');
    }

    public function test_expense_currency_is_persisted_and_totals_and_trends_are_separate(): void
    {
        $expenseDate = now()->toDateString();

        $this->actingAs($this->mainManager)->post(route('expenses.store'), [
            'expense_date' => $expenseDate,
            'description' => 'Afghani office rent',
            'amount' => 1000,
            'currency' => 'AFN',
            'payment_method' => 'bank',
            'status' => 'paid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('expenses.store'), [
            'expense_date' => $expenseDate,
            'description' => 'USD software subscription',
            'amount' => 25.50,
            'currency' => 'USD',
            'payment_method' => 'card',
            'status' => 'unpaid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Afghani office rent',
            'amount' => 1000,
            'currency' => 'AFN',
        ]);
        $this->assertDatabaseHas('expenses', [
            'description' => 'USD software subscription',
            'amount' => 25.50,
            'currency' => 'USD',
        ]);

        $this->get(route('expenses.index', ['from' => $expenseDate, 'to' => $expenseDate]))
            ->assertOk()
            ->assertSee('؋1,000.00')
            ->assertSee('$25.50')
            ->assertSee('data-expense-currency="AFN"', false)
            ->assertSee('data-expense-currency="USD"', false)
            ->assertSee('Weekly expenses — AFN', false);

        $expense = Expense::where('description', 'USD software subscription')->firstOrFail();
        $this->put(route('expenses.update', $expense), [
            'expense_date' => $expenseDate,
            'description' => $expense->description,
            'amount' => 30,
            'currency' => 'AFN',
            'payment_method' => 'card',
            'status' => 'paid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 30,
            'currency' => 'AFN',
        ]);
    }

    public function test_expense_trend_query_does_not_inherit_list_ordering_or_pagination(): void
    {
        $trendQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$trendQueries): void {
            if (str_contains($query->sql, 'expense_date, SUM(amount) as amount')) {
                $trendQueries[] = $query->sql;
            }
        });

        $this->actingAs($this->mainManager)
            ->get(route('expenses.index'))
            ->assertOk();

        $this->assertNotEmpty($trendQueries);
        $trendQuery = strtolower(end($trendQueries));
        $this->assertStringNotContainsString('limit', $trendQuery);
        preg_match('/order by (.*)$/', $trendQuery, $orderBy);
        $this->assertArrayHasKey(1, $orderBy);
        $this->assertStringNotContainsString('id', $orderBy[1]);
    }

    private function createSale(Branch $branch, User $cashier, string $invoice, Product $product): Sale
    {
        $sale = Sale::create([
            'branch_id' => $branch->id,
            'invoice_no' => $invoice,
            'user_id' => $cashier->id,
            'subtotal' => 8,
            'total' => 8,
            'paid' => 8,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 8,
            'cost' => 4,
            'quantity' => 1,
            'discount' => 0,
            'tax' => 0,
            'subtotal' => 8,
        ]);

        return $sale;
    }

    private function userInBranch(Branch $branch, string $role, string $email): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => $email,
            'is_active' => true,
        ]);
        $user->syncRoles($role);

        return $user;
    }
}
