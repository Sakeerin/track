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

/*
|--------------------------------------------------------------------------
| Notification Subscription Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Api\SubscriptionController;

// Create notification subscription
Route::post('/subscriptions', [SubscriptionController::class, 'store'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.store');

// Get subscriptions for a shipment
Route::get('/subscriptions', [SubscriptionController::class, 'index'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.index');

// Update subscription preferences
Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.update');

// Unsubscribe endpoint (also available via web route)
Route::get('/subscriptions/unsubscribe/{token}', [SubscriptionController::class, 'unsubscribe'])
    ->name('api.unsubscribe');

// Get subscription analytics
Route::get('/subscriptions/{id}/analytics', [SubscriptionController::class, 'analytics'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.analytics');

// Update notification preferences
Route::put('/subscriptions/{id}/preferences', [SubscriptionController::class, 'updatePreferences'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.preferences');

// Get delivery tracking for a shipment
Route::get('/subscriptions/delivery-tracking', [SubscriptionController::class, 'deliveryTracking'])
    ->middleware(['throttle:api'])
    ->name('subscriptions.delivery-tracking');

// Mark notification as delivered (webhook callback)
Route::post('/notifications/{logId}/delivered', [SubscriptionController::class, 'markDelivered'])
    ->middleware(['throttle:api'])
    ->name('notifications.delivered');