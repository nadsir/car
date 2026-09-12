<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EffectiveCategoryFilterController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminAttributeController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\FilteredProductController;
use App\Http\Controllers\Api\AdminProductImageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/tree', [CategoryController::class, 'index']);
Route::get(
    '/categories/{slug}/filters',
    [EffectiveCategoryFilterController::class, 'index']
);
Route::get('/products', [FilteredProductController::class, 'index']);
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::get('/categories/{category}/attributes', [AdminCategoryController::class, 'attributes']);
    Route::put('/categories/{category}/attributes', [AdminCategoryController::class, 'attributes']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::get('/attributes', [AdminAttributeController::class, 'index']);
    Route::post('/attributes', [AdminAttributeController::class, 'store']);
    Route::put('/attributes/{attribute}', [AdminAttributeController::class, 'update']);
    Route::delete('/attributes/{attribute}', [AdminAttributeController::class, 'destroy']);
    Route::post('/attributes/{attribute}/values', [AdminAttributeController::class, 'storeValue']);
    Route::put('/attributes/{attribute}/values/{value}', [AdminAttributeController::class, 'updateValue']);
    Route::delete('/attributes/{attribute}/values/{value}', [AdminAttributeController::class, 'destroyValue']);
    Route::get('/products/meta', [AdminProductController::class, 'meta']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post(
        '/products/{product}/images',
        [AdminProductImageController::class, 'store']
    );
    Route::delete(
        '/product-images/{image}',
        [AdminProductImageController::class, 'destroy']
    );
    Route::patch(
        '/products/{product}/images/{image}/primary',
        [AdminProductImageController::class, 'setPrimary']
    );
    Route::put(
        '/products/{product}/images/reorder',
        [AdminProductImageController::class, 'reorder']
    );
    Route::delete(
        '/products/{product}/images/{image}',
        [AdminProductImageController::class, 'destroy']
    );
});
