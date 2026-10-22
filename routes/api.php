<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsumptionReportController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductSupplierController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::post('products', [ProductController::class, 'store'])->middleware('can:manage-inventory-catalog');

    Route::get('products/{product}/movements', [StockMovementController::class, 'index']);
    Route::post('products/{product}/movements', [StockMovementController::class, 'store']);

    Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);
    Route::post('suppliers', [SupplierController::class, 'store'])->middleware('can:manage-inventory-catalog');
    Route::get('products/{product}/suppliers', [ProductSupplierController::class, 'index']);
    Route::put('products/{product}/suppliers/{supplier}', [ProductSupplierController::class, 'update']);

    Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
    Route::post('departments', [DepartmentController::class, 'store'])->middleware('can:manage-inventory-catalog');
    Route::get('reports/consumption', ConsumptionReportController::class);
});
