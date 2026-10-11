<?php

use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use Illuminate\Support\Facades\Route;

// Home page is only a "coming soon" page, so bots find nothing here.
Route::view('/', 'home');

Route::middleware('guest')->group(function () {
    Route::get('/mainadmin', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/mainadmin', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [BillController::class, 'dashboard'])->name('dashboard');
    Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('/bills/{id}', [BillController::class, 'show'])->name('bills.show');
    Route::get('/bills/{id}/edit', [BillController::class, 'edit'])->name('bills.edit');
    Route::put('/bills/{id}', [BillController::class, 'update'])->name('bills.update');
    Route::delete('/bills/{id}', [BillController::class, 'destroy'])->name('bills.destroy');
    Route::post('/bills/{id}/fetch', [BillController::class, 'fetch'])->name('bills.fetch');
    Route::post('/bills/{id}/items/{itemId}/paid', [BillController::class, 'togglePaid'])->name('bills.paid');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/password', [AccountController::class, 'password'])->name('account.password');
    Route::post('/account/token', [AccountController::class, 'rotateToken'])->name('account.token');
    Route::delete('/account/token', [AccountController::class, 'revokeToken'])->name('account.token.revoke');

    Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
        Route::get('providers', [ProviderController::class, 'index'])->name('providers');
        Route::post('providers/mapping', [ProviderController::class, 'saveMapping'])->name('providers.mapping');
        Route::post('providers/{provider}/settings', [ProviderController::class, 'saveSettings'])->name('providers.settings');

        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::post('users/{user}/password', [UserController::class, 'password'])->name('users.password');
        Route::delete('users/{user}/token', [UserController::class, 'revokeToken'])->name('users.token.revoke');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
