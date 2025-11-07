<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventIngestionController;
use App\Http\Controllers\Api\TrackingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Public Tracking Routes
|--------------------------------------------------------------------------
*/

// Tracking service health check (must be before the wildcard route)
Route::get('/tracking/health', [TrackingController::class, 'health'])
    ->name('tracking.health');

// Multi-shipment tracking endpoint
Route::post('/tracking', [TrackingController::class, 'track'])
    ->middleware(['throttle:tracking'])
    ->name('tracking.multi');

// Single shipment tracking endpoint (SEO-friendly)
Route::get('/tracking/{trackingNumber}', [TrackingController::class, 'trackSingle'])
    ->middleware(['throttle:tracking'])
    ->name('tracking.single')
    ->where('trackingNumber', '[A-Z]{2}[0-9]{10}');

/*
|--------------------------------------------------------------------------
| Event Ingestion Routes
|--------------------------------------------------------------------------
*/

// Webhook endpoint for receiving events from handhelds and partners
Route::post('/events/webhook', [EventIngestionController::class, 'receiveWebhook'])
    ->middleware(['hmac.signature', 'throttle:webhook'])
    ->name('events.webhook');

// Batch upload endpoint for CSV files
Route::post('/events/batch', [EventIngestionController::class, 'processBatch'])
    ->middleware(['api.key', 'throttle:batch'])
    ->name('events.batch');

// Health check endpoint
Route::get('/events/health', [EventIngestionController::class, 'health'])
    ->name('events.health');