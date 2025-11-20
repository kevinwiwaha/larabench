<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    $driver = DB::getDriverName();
    $connection = config('database.default');
    
    // Get database version
    try {
        if ($driver === 'pgsql') {
            $version = DB::selectOne('SELECT version()')->version;
            $version = explode(' ', $version)[1] ?? 'Unknown';
        } else {
            $version = DB::selectOne('SELECT VERSION() as version')->version;
        }
    } catch (\Exception $e) {
        $version = 'Unable to connect';
    }
    
    // Get some stats
    try {
        $stats = [
            'users' => DB::table('users')->count(),
            'products' => DB::table('products')->count(),
            'orders' => DB::table('orders')->count(),
            'products_in_stock' => DB::table('products')->where('stock', '>', 0)->count(),
        ];
    } catch (\Exception $e) {
        $stats = null;
    }
    
    return view('welcome', [
        'driver' => $driver,
        'connection' => $connection,
        'version' => $version,
        'stats' => $stats,
    ]);
});
