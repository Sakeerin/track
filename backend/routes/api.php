<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventIngestionController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\Admin\AdminShipmentController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminConfigController;

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

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public auth routes
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::get('/oauth/{provider}', [AuthController::class, 'redirectToProvider'])->name('auth.oauth.redirect');
    Route::post('/oauth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->name('auth.oauth.callback');

    // Authenticated auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout.all');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    });
});

// Legacy route for backwards compatibility
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
    ->middleware(['throttle:tracking', 'recaptcha'])
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

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.ip'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Dashboard & Monitoring (readonly role can access)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,ops,cs,readonly'])->group(function () {
        Route::get('/dashboard/health', [AdminDashboardController::class, 'health'])->name('admin.health');
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats'])->name('admin.stats');
        Route::get('/dashboard/events', [AdminDashboardController::class, 'eventMetrics'])->name('admin.events');
        Route::get('/dashboard/sla', [AdminDashboardController::class, 'slaMetrics'])->name('admin.sla');
        Route::get('/dashboard/queues', [AdminDashboardController::class, 'queueStatus'])->name('admin.queues');
    });

    /*
    |--------------------------------------------------------------------------
    | Shipment Search & View (cs role can access)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,ops,cs'])->group(function () {
        Route::get('/shipments', [AdminShipmentController::class, 'search'])->name('admin.shipments.search');
        Route::get('/shipments/{id}', [AdminShipmentController::class, 'show'])->name('admin.shipments.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Shipment Management (ops role can access)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,ops'])->group(function () {
        Route::post('/shipments/{id}/events', [AdminShipmentController::class, 'addEvent'])->name('admin.shipments.events.create');
        Route::put('/shipments/{shipmentId}/events/{eventId}', [AdminShipmentController::class, 'updateEvent'])->name('admin.shipments.events.update');
        Route::delete('/shipments/{shipmentId}/events/{eventId}', [AdminShipmentController::class, 'deleteEvent'])->name('admin.shipments.events.delete');
        Route::post('/shipments/export', [AdminShipmentController::class, 'export'])->name('admin.shipments.export');
    });

    /*
    |--------------------------------------------------------------------------
    | User Management (admin only)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/roles', [AdminUserController::class, 'roles'])->name('admin.users.roles');
        Route::get('/{id}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::post('/', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::put('/{id}/roles', [AdminUserController::class, 'updateRoles'])->name('admin.users.roles.update');
        Route::post('/{id}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('admin.users.toggle');
        Route::delete('/{id}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Configuration Management (admin only)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])->prefix('config')->group(function () {
        Route::get('/facilities', [AdminConfigController::class, 'facilities'])->name('admin.config.facilities');
        Route::post('/facilities', [AdminConfigController::class, 'createFacility'])->name('admin.config.facilities.create');
        Route::put('/facilities/{id}', [AdminConfigController::class, 'updateFacility'])->name('admin.config.facilities.update');
        Route::get('/event-codes', [AdminConfigController::class, 'eventCodes'])->name('admin.config.event-codes');
        Route::get('/eta-rules', [AdminConfigController::class, 'etaRules'])->name('admin.config.eta-rules');
        Route::get('/system', [AdminConfigController::class, 'systemConfig'])->name('admin.config.system');
    });

    /*
    |--------------------------------------------------------------------------
    | Audit Logs (admin only)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('admin.audit-logs');
    });
});
