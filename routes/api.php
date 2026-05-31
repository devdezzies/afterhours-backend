<?php

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/orders', [OrderController::class, 'index']);
});

Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{id}', [AdminProductController::class, 'show']);
        Route::post('/products', [AdminProductController::class, 'create']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
        Route::get('/stock-alerts', [AdminProductController::class, 'stockAlerts']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/stats', [AdminOrderController::class, 'stats']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/orders/update-status/{id}', [AdminOrderController::class, 'update']);
    });
});
