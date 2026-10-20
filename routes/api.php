<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show']);

Route::get('products/{product}/movements', [StockMovementController::class, 'index']);
Route::post('products/{product}/movements', [StockMovementController::class, 'store']);
