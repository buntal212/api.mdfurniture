<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/login', [LoginController::class, 'store']);

    Route::apiResource('categories', CategoryController::class)
        ->middleware('auth:sanctum');

    Route::apiResource('products', ProductController::class)
        ->middleware('auth:sanctum');

});

Route::prefix('v2/catalog')->middleware('throttle:60,1')->group(function () {
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/products', [CatalogController::class, 'products']);
    Route::get('/thumbnails/{filename}', [CatalogController::class, 'thumbnail'])
        ->where('filename', '[A-Za-z0-9_-]+\\.webp')
        ->middleware('cache.headers:public;max_age=31536000;immutable');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
