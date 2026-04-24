<?php

use App\Http\Controllers\BackupUploadController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\GuestPaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// Guest-facing routes
Route::get('/', [GuestController::class, 'home'])->name('guest.home');
Route::get('/rooms', [GuestController::class, 'rooms'])->name('guest.rooms');
Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])->name('guest.room-detail');
Route::get('/virtual-tours', [GuestController::class, 'virtualTours'])->name('guest.virtual-tours');
Route::get('/reserve', [GuestController::class, 'reserveForm'])->name('guest.reserve');
Route::post('/reserve', [GuestController::class, 'reserveSubmit'])
    ->middleware(['throttle:5,1', \Spatie\Honeypot\ProtectAgainstSpam::class])
    ->name('guest.reserve.submit');
Route::get('/track', [GuestController::class, 'track'])
    ->middleware('throttle:10,1')
    ->name('guest.track');
Route::get('/track/secure/{reservation}', [GuestController::class, 'trackSecure'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('guest.track.secure');

// Virtual Tour API endpoints
Route::prefix('api/tour')->group(function () {
    Route::get('/waypoints', [TourController::class, 'waypoints'])->name('api.tour.waypoints');
    Route::get('/waypoint/{slug}', [TourController::class, 'waypoint'])->name('api.tour.waypoint');
    Route::get('/room-type/{id}/availability', [TourController::class, 'roomTypeAvailability'])->name('api.tour.room-type-availability');
    Route::get('/room/{id}/availability', [TourController::class, 'roomAvailability'])->name('api.tour.room-availability');
    Route::post('/reserve', [TourController::class, 'reserveSubmit'])
        ->middleware(['throttle:5,1', \Spatie\Honeypot\ProtectAgainstSpam::class])
        ->name('api.tour.reserve');
});

// Tour viewer page
Route::get('/tour/{slug?}', [TourController::class, 'viewer'])->name('guest.tour.viewer');

// Guest payment routes (online payments - TESTING)
Route::prefix('reserve/pay')->middleware(['throttle:10,1'])->group(function () {
    Route::get('/{token}', [GuestPaymentController::class, 'showPaymentPage'])->name('guest.payment.show');
    Route::post('/{token}', [GuestPaymentController::class, 'initializePayment'])->name('guest.payment.initialize');
});
Route::get('/reserve/payment-success', [GuestPaymentController::class, 'paymentSuccess'])->name('guest.payment.success');
Route::get('/reserve/payment-failed', [GuestPaymentController::class, 'paymentFailed'])->name('guest.payment.failed');

// PayMongo webhook endpoint (TESTING - must be excluded from CSRF)
Route::post('/api/webhooks/paymongo', [PaymentWebhookController::class, 'handle'])
    ->middleware(['throttle:100,1'])
    ->name('webhook.paymongo');

// Backup upload (standard POST, bypasses Livewire)
Route::post('/admin/backup-upload', [BackupUploadController::class, 'upload'])
    ->middleware(['web', 'auth'])
    ->name('backup.upload');
