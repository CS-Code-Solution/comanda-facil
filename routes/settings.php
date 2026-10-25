<?php

use App\Http\Controllers\OwnPinController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::inertia('settings/profile', 'settings/profile')->name('profile.edit');
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::get('settings/pin', [OwnPinController::class, 'edit'])->name('pin.edit');
    Route::put('settings/pin', [OwnPinController::class, 'update'])->middleware('throttle:staff-sensitive')->name('pin.update');
});
