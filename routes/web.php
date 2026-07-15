<?php

use App\Http\Controllers\BackupUploadController;
use App\Http\Controllers\AdminMfaController;
use App\Http\Controllers\AlternativeRoomOfferController;
use App\Http\Controllers\Guest\AuthController as GuestAuthController;
use App\Http\Controllers\Guest\DashboardController as GuestDashboardController;
use App\Http\Controllers\Guest\FeedbackController as GuestFeedbackController;
use App\Http\Controllers\Guest\PasswordResetController as GuestPasswordResetController;
use App\Http\Controllers\Guest\ProfileController as GuestProfileController;
use App\Http\Controllers\Guest\SupportThreadController as GuestSupportThreadController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\GuestPaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// Guest-facing routes
Route::get('/', [GuestController::class, 'home'])->name('guest.home');
Route::get('/about-us', [GuestController::class, 'about'])->name('guest.about');
Route::get('/rooms', [GuestController::class, 'rooms'])->name('guest.rooms');
Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])->name('guest.room-detail');
Route::get('/virtual-tours', [GuestController::class, 'virtualTours'])->name('guest.virtual-tours');
Route::get('/support', function () {
    if (auth('guest')->check()) {
        return redirect()->route('guest.account.support.index');
    }

    return view('guest.support');
})->name('guest.support');
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
Route::get('/alternative-offers/{offer}', [AlternativeRoomOfferController::class, 'show'])->name('guest.alternative-offers.show');
Route::post('/alternative-offers/{offer}/accept', [AlternativeRoomOfferController::class, 'accept'])->middleware('throttle:10,1')->name('guest.alternative-offers.accept');
Route::post('/alternative-offers/{offer}/decline', [AlternativeRoomOfferController::class, 'decline'])->middleware('throttle:10,1')->name('guest.alternative-offers.decline');

Route::prefix('account')->middleware('throttle:20,1')->group(function () {
    Route::middleware('guest:guest')->group(function () {
        Route::get('/register', [GuestAuthController::class, 'showRegister'])->name('guest.account.register');
        Route::post('/register', [GuestAuthController::class, 'register'])->middleware(\Spatie\Honeypot\ProtectAgainstSpam::class)->name('guest.account.register.submit');
        Route::get('/login', [GuestAuthController::class, 'showLogin'])->name('guest.account.login');
        Route::post('/login', [GuestAuthController::class, 'login'])->name('guest.account.login.submit');
        Route::get('/forgot-password', [GuestPasswordResetController::class, 'showRequest'])->name('guest.account.password.request');
        Route::post('/forgot-password', [GuestPasswordResetController::class, 'send'])->name('guest.account.password.email');
        Route::get('/reset-password/{token}', [GuestPasswordResetController::class, 'showReset'])->name('guest.account.password.reset');
        Route::post('/reset-password', [GuestPasswordResetController::class, 'reset'])->name('guest.account.password.update');
    });

    Route::get('/verify/{account}', [GuestAuthController::class, 'verify'])
        ->name('guest.account.verify');

    Route::middleware('auth:guest')->group(function () {
        Route::post('/logout', [GuestAuthController::class, 'logout'])->name('guest.account.logout');
        Route::post('/verification', [GuestAuthController::class, 'resendVerification'])->name('guest.account.verification.send');
        Route::get('/dashboard', [GuestDashboardController::class, 'dashboard'])->name('guest.account.dashboard');
        Route::get('/profile', [GuestProfileController::class, 'edit'])->name('guest.account.profile');
        Route::put('/profile', [GuestProfileController::class, 'update'])->name('guest.account.profile.update');
        Route::get('/reservations', [GuestDashboardController::class, 'reservations'])->name('guest.account.reservations');
        Route::post('/reservations/claim', [GuestDashboardController::class, 'claim'])->name('guest.account.reservations.claim');
        Route::get('/reservations/{reservation}/deposit-payment', [GuestDashboardController::class, 'startDepositPayment'])->name('guest.account.reservations.deposit-payment');
        Route::get('/reservations/{reservation}/check-in-payment/qr', [QrCodeController::class, 'accountCheckInBalanceQr'])->name('guest.account.reservations.check-in-payment.qr');
        Route::get('/reservations/{reservation}/check-in-payment', [QrCodeController::class, 'accountCheckInBalanceCheckout'])->name('guest.account.reservations.check-in-payment.checkout');
        Route::get('/reservations/{reservation}/feedback', [GuestFeedbackController::class, 'create'])->name('guest.account.feedback.create');
        Route::post('/reservations/{reservation}/feedback', [GuestFeedbackController::class, 'store'])->name('guest.account.feedback.store');
        Route::get('/reservations/{reservation}', [GuestDashboardController::class, 'showReservation'])->name('guest.account.reservations.show');
        Route::get('/support', [GuestSupportThreadController::class, 'index'])->name('guest.account.support.index');
        Route::post('/support', [GuestSupportThreadController::class, 'store'])->middleware('throttle:5,1')->name('guest.account.support.submit');
        Route::get('/support/{inquiry}/messages', [GuestSupportThreadController::class, 'messages'])->name('guest.account.support.messages');
        Route::get('/support/{inquiry}', [GuestSupportThreadController::class, 'show'])->name('guest.account.support.show');
        Route::post('/support/{inquiry}/reply', [GuestSupportThreadController::class, 'reply'])->middleware('throttle:10,1')->name('guest.account.support.reply');
    });
});

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
Route::get('/reserve/check-in-payment/{token}/result', [GuestPaymentController::class, 'checkInBalancePaymentResult'])
    ->middleware('throttle:20,1')
    ->name('guest.check-in-payment.result');
Route::get('/reserve/payment-qr/{token}', [QrCodeController::class, 'payment'])->name('guest.payment.qr');

// PayMongo webhook endpoint (TESTING - must be excluded from CSRF)
Route::post('/api/webhooks/paymongo', [PaymentWebhookController::class, 'handle'])
    ->middleware(['throttle:100,1'])
    ->name('webhook.paymongo');

// Backup upload (standard POST, bypasses Livewire)
Route::post('/admin/backup-upload', [BackupUploadController::class, 'upload'])
    ->middleware(['web', 'auth'])
    ->name('backup.upload');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/mfa-challenge', [AdminMfaController::class, 'challenge'])->name('admin.mfa.challenge');
        Route::post('/mfa-challenge', [AdminMfaController::class, 'verifyChallenge'])->name('admin.mfa.challenge.verify');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/mfa-setup', [AdminMfaController::class, 'setup'])->name('admin.mfa.setup');
        Route::post('/mfa-setup/enable', [AdminMfaController::class, 'enable'])->name('admin.mfa.enable');
        Route::post('/mfa-setup/confirm', [AdminMfaController::class, 'confirm'])->name('admin.mfa.confirm');
        Route::post('/mfa-setup/recovery-codes', [AdminMfaController::class, 'regenerate'])->name('admin.mfa.recovery-codes');
        Route::delete('/mfa-setup', [AdminMfaController::class, 'disable'])->name('admin.mfa.disable');
        Route::get('/mfa-confirm', [AdminMfaController::class, 'confirmRecent'])->name('admin.mfa.recent');
        Route::post('/mfa-confirm', [AdminMfaController::class, 'verifyRecent'])->name('admin.mfa.recent.verify');
    });
});
Route::get('/admin/qr-code', [QrCodeController::class, 'encrypted'])
    ->middleware(['web', 'auth'])
    ->name('admin.qr-code');
