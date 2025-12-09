<?php

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