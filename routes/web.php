<?php

use App\Http\Controllers\BackupUploadController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// Guest-facing routes
Route::get('/', [GuestController::class, 'home'])->name('guest.home');
Route::get('/rooms', [GuestController::class, 'rooms'])->name('guest.rooms');
Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])->name('guest.room-detail');
Route::get('/virtual-tours', [GuestController::class, 'virtualTours'])->name('guest.virtual-tours');
Route::get('/reserve', [GuestController::class, 'reserveForm'])->name('guest.reserve');
Route::post('/reserve', [GuestController::class, 'reserveSubmit'])->name('guest.reserve.submit');
Route::get('/track', [GuestController::class, 'track'])->name('guest.track');

// Virtual Tour API endpoints
Route::prefix('api/tour')->group(function () {
    Route::get('/waypoints', [TourController::class, 'waypoints'])->name('api.tour.waypoints');
    Route::get('/waypoint/{slug}', [TourController::class, 'waypoint'])->name('api.tour.waypoint');
    Route::get('/room-type/{id}/availability', [TourController::class, 'roomTypeAvailability'])->name('api.tour.room-availability');
    Route::post('/reserve', [TourController::class, 'reserveSubmit'])->name('api.tour.reserve');
});

// Tour viewer page
Route::get('/tour/{slug?}', [TourController::class, 'viewer'])->name('guest.tour.viewer');

// Backup upload (standard POST, bypasses Livewire)
Route::post('/admin/backup-upload', [BackupUploadController::class, 'upload'])
    ->middleware(['web', 'auth'])
    ->name('backup.upload');
