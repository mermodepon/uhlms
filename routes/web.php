<?php

use App\Http\Controllers\BackupUploadController;
use App\Http\Controllers\GuestController;
use Illuminate\Support\Facades\Route;

// Guest-facing routes
Route::get('/', [GuestController::class, 'home'])->name('guest.home');
Route::get('/rooms', [GuestController::class, 'rooms'])->name('guest.rooms');
Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])->name('guest.room-detail');
Route::get('/virtual-tours', [GuestController::class, 'virtualTours'])->name('guest.virtual-tours');
Route::get('/reserve', [GuestController::class, 'reserveForm'])->name('guest.reserve');
Route::post('/reserve', [GuestController::class, 'reserveSubmit'])->name('guest.reserve.submit');
Route::get('/track', [GuestController::class, 'track'])->name('guest.track');

// Backup upload (standard POST, bypasses Livewire)
Route::post('/admin/backup-upload', [BackupUploadController::class, 'upload'])
    ->middleware(['web', 'auth'])
    ->name('backup.upload');
