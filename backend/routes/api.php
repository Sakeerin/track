<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public tracking routes
Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    });
    
    // Tracking endpoints (rate limited)
    Route::middleware(['throttle:100,1'])->group(function () {
        Route::post('/track', function () {
            return response()->json(['message' => 'Tracking endpoint - to be implemented']);
        });
        
        Route::get('/track/{trackingNumber}', function ($trackingNumber) {
            return response()->json([
                'message' => 'Single tracking endpoint - to be implemented',
                'tracking_number' => $trackingNumber
            ]);
        });
    });
    
    // Event ingestion endpoints
    Route::middleware(['throttle:1000,1'])->group(function () {
        Route::post('/events/webhook', function () {
            return response()->json(['message' => 'Webhook endpoint - to be implemented']);
        });
        
        Route::post('/events/batch', function () {
            return response()->json(['message' => 'Batch upload endpoint - to be implemented']);
        });
    });
});