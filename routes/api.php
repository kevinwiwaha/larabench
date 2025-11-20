<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Benchmark endpoints
Route::get('/products', [ProductController::class, 'index']);   // READ-heavy
Route::post('/orders', [OrderController::class, 'store']);      // WRITE-heavy

