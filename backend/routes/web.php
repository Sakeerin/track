<?php

use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/track/{trackingNumber}', [PublicTrackingController::class, 'show'])
    ->name('public.track')
    ->where('trackingNumber', '[A-Z]{2}[0-9]{10}');

Route::get('/faq', [PublicTrackingController::class, 'faq'])
    ->name('public.faq');

Route::get('/contact', [PublicTrackingController::class, 'contact'])
    ->name('public.contact');

Route::post('/contact', [PublicTrackingController::class, 'submitContact'])
    ->name('public.contact.submit');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])
    ->name('public.sitemap');

// Unsubscribe route for notification management
Route::get('/unsubscribe/{token}', function ($token) {
    $success = \App\Models\Subscription::unsubscribeByToken($token);
    
    if ($success) {
        return response()->json([
            'message' => 'Successfully unsubscribed from notifications',
        ]);
    }
    
    return response()->json([
        'message' => 'Invalid or expired unsubscribe token',
    ], 404);
})->name('unsubscribe');
