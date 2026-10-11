<?php

use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\GatewayController;
use Illuminate\Support\Facades\Route;

// Public: tells that the API is alive. No data.
Route::get('/', [GatewayController::class, 'index']);

Route::prefix('v1')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Gateway (nothing is saved)
    Route::get('bill-types', [GatewayController::class, 'types']);
    Route::match(['get', 'post'], 'inquiry', [GatewayController::class, 'inquire']);

    // Saved bills of the token owner
    Route::get('bills', [BillController::class, 'index']);
    Route::post('bills', [BillController::class, 'store']);
    Route::get('bills/{id}', [BillController::class, 'show']);
    Route::match(['put', 'patch'], 'bills/{id}', [BillController::class, 'update']);
    Route::delete('bills/{id}', [BillController::class, 'destroy']);
    Route::post('bills/{id}/fetch', [BillController::class, 'fetch']);
    Route::get('bills/{id}/items', [BillController::class, 'items']);
    Route::match(['put', 'patch'], 'bills/{id}/items/{itemId}', [BillController::class, 'updateItem']);
});
