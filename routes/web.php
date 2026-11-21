<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// ---- Language switch (available to everyone) ----
Route::get('locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// ---- Guest ----
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---- Authenticated ----
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    // Profile (self-service, any authenticated user)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::middleware('role:Super Admin')->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('branches/active', [BranchController::class, 'switch'])->name('branches.switch');
    });

    // ---- POS ----
    Route::middleware('permission:pos.access')->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/lookup', [PosController::class, 'lookup'])->name('pos.lookup');
        Route::get('pos/catalog', [PosController::class, 'catalog'])->name('pos.catalog');
        Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::post('pos/sync', [PosController::class, 'sync'])->name('pos.sync');
        Route::get('pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::post('pos/drawer', [PosController::class, 'drawer'])->name('pos.drawer');
        Route::post('pos/hold', [PosController::class, 'hold'])->name('pos.hold');
        Route::post('pos/resume/{held}', [PosController::class, 'resume'])->name('pos.resume');
    });

    // ---- Cash register / shifts ----
    Route::middleware('permission:cash.manage')->group(function () {
        Route::get('register', [CashSessionController::class, 'index'])->name('register.index');
        Route::post('register/open', [CashSessionController::class, 'open'])->name('register.open');
        Route::post('register/{session}/close', [CashSessionController::class, 'close'])->name('register.close');
        Route::get('register/{session}', [CashSessionController::class, 'show'])->name('register.show');
    });

    // ---- Products ----
    Route::middleware('permission:products.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware('permission:products.create')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
    });
    Route::middleware('permission:products.edit')->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    });
    Route::delete('products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')->name('products.destroy');

    // ---- Categories / Units ----
    Route::middleware('permission:categories.manage')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
    Route::middleware('permission:units.manage')->group(function () {
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // ---- Suppliers ----
    Route::middleware('permission:suppliers.manage')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // ---- Customers ----
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('permission:customers.manage')->group(function () {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // ---- Purchases ----
    Route::middleware('permission:purchases.view')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    });
    Route::middleware('permission:purchases.manage')->group(function () {
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('purchases/{purchase}/payments', [PurchaseController::class, 'recordPayment'])->name('purchases.payments.store');
    });
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware('permission:purchases.view')->name('purchases.show');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])
        ->middleware('permission:purchases.manage')->name('purchases.destroy');
    Route::get('reports/purchases/export/{format}', [ReportController::class, 'exportPurchases'])
        ->middleware('permission:purchases.view')->name('reports.purchases.export');

    // ---- Expenses ----
    Route::middleware('permission:expenses.view')->prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('export/{format}', [ReportController::class, 'exportExpenses'])->name('export');
    });
    Route::middleware('permission:expenses.manage')->group(function () {
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
        Route::delete('expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');
    });

    // ---- Inventory ----
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
    });
    Route::post('inventory/{product}/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:inventory.adjust')->name('inventory.adjust');
    Route::put('inventory/{product}/threshold', [InventoryController::class, 'updateThreshold'])
        ->middleware('permission:inventory.adjust')->name('inventory.threshold');

    Route::middleware('permission:inventory.transfers')->prefix('stock-transfers')->name('transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('create', [StockTransferController::class, 'create'])->name('create');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('{transfer}/receive', [StockTransferController::class, 'receiveForm'])->name('receive.form');
        Route::post('{transfer}/receive', [StockTransferController::class, 'receive'])->name('receive');
        Route::post('{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('dispatch');
        Route::post('{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel');
        Route::get('{transfer}', [StockTransferController::class, 'show'])->name('show');
    });

    Route::middleware('role:Super Admin')->prefix('stock-transfers')->name('transfers.')->group(function () {
        Route::post('{transfer}/approve', [StockTransferController::class, 'approve'])->name('approve');
        Route::post('{transfer}/reject', [StockTransferController::class, 'reject'])->name('reject');
    });

    // ---- Sales ----
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    });
    Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])
        ->middleware('permission:sales.refund')->name('sales.refund');

    // ---- Reports ----
    Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('sales/export/{format}', [ReportController::class, 'exportSales'])->name('sales.export');
        Route::get('profit', [ReportController::class, 'profit'])->name('profit');
        Route::get('profit/export/{format}', [ReportController::class, 'exportProfit'])->name('profit.export');
        Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('inventory/export/{format}', [ReportController::class, 'exportInventory'])->name('inventory.export');
        Route::get('top-products', [ReportController::class, 'topProducts'])->name('top');
    });

    // ---- Users ----
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ---- Roles ----
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // ---- Settings ----
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // ---- Backups ----
    Route::middleware('permission:backups.manage')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    // ---- Activity log ----
    Route::get('activity', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity.view')->name('activity.index');
});
