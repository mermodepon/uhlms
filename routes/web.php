<?php

use App\Http\Controllers\GuestController;
use App\Http\Controllers\Guest\MessageController;
use Illuminate\Support\Facades\Route;

// Guest-facing routes
Route::get('/', [GuestController::class, 'home'])->name('guest.home');
Route::get('/rooms', [GuestController::class, 'rooms'])->name('guest.rooms');
Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])->name('guest.room-detail');
Route::get('/virtual-tours', [GuestController::class, 'virtualTours'])->name('guest.virtual-tours');
Route::get('/reserve', [GuestController::class, 'reserveForm'])->name('guest.reserve');
Route::post('/reserve', [GuestController::class, 'reserveSubmit'])->name('guest.reserve.submit');
Route::get('/track', [GuestController::class, 'track'])->name('guest.track');
Route::get('/messages', [MessageController::class, 'index'])->name('guest.messages');
Route::post('/messages', [MessageController::class, 'store'])->name('guest.messages.store');
Route::post('/contact', [MessageController::class, 'storeInquiry'])->name('guest.contact.store');

