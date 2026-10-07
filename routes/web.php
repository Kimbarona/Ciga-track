<?php

use App\Http\Controllers\Staff\DistributionController;
use App\Http\Controllers\Staff\ExpenseController;
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\MyEntriesController;
use App\Http\Controllers\Staff\PreviewController;
use App\Http\Controllers\Staff\PurchaseController;
use App\Http\Controllers\Staff\RemittanceController;
use App\Http\Controllers\Staff\ResellerController;
use App\Http\Controllers\Staff\StockController;
use Illuminate\Support\Facades\Route;

/*
| Staff app (React + Inertia) — every role. Admin-only screens live in Filament (/admin).
*/
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('distributions/new', [DistributionController::class, 'create'])->name('distributions.create');
    Route::post('distributions', [DistributionController::class, 'store'])->name('distributions.store');
    Route::get('distributions/{distribution}/edit', [DistributionController::class, 'edit'])->name('distributions.edit');
    Route::put('distributions/{distribution}', [DistributionController::class, 'update'])->name('distributions.update');

    Route::get('remittances/new', [RemittanceController::class, 'create'])->name('remittances.create');
    Route::post('remittances', [RemittanceController::class, 'store'])->name('remittances.store');
    Route::get('remittances/{remittance}/edit', [RemittanceController::class, 'edit'])->name('remittances.edit');
    Route::put('remittances/{remittance}', [RemittanceController::class, 'update'])->name('remittances.update');

    Route::get('purchases/new', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');

    Route::get('expenses/new', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');

    Route::get('stocks', StockController::class)->name('stocks');
    Route::get('resellers', [ResellerController::class, 'index'])->name('resellers.index');
    Route::get('resellers/{reseller}', [ResellerController::class, 'show'])->name('resellers.show');
    Route::get('my-entries', MyEntriesController::class)->name('my-entries');
    Route::inertia('profile', 'profile')->name('profile');

    // JSON lookups for live previews
    Route::get('lookup/price', [PreviewController::class, 'price'])->name('lookup.price');
    Route::get('lookup/resellers/{reseller}/balance', [PreviewController::class, 'balance'])->name('lookup.balance');
});

require __DIR__.'/settings.php';
