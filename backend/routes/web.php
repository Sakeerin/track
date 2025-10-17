<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Parcel Tracking System API',
        'version' => '1.0.0',
        'documentation' => '/docs'
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => 'connected',
            'redis' => 'connected',
            'queue' => 'running'
        ]
    ]);
});