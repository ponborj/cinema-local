<?php

use App\Http\Controllers\ConversionController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', LibraryController::class)->name('library');
Route::get('/watch', [MediaController::class, 'watch'])->name('media.watch');
Route::get('/media', [MediaController::class, 'stream'])->name('media.stream');
Route::get('/subtitle', [MediaController::class, 'subtitle'])->name('media.subtitle');
Route::post('/api/conversions', [ConversionController::class, 'store'])->middleware('throttle:15,1')->name('conversions.store');
Route::get('/api/conversions/{conversion}', [ConversionController::class, 'show'])->where('conversion', '[a-f0-9]{64}')->name('conversions.show');
Route::post('/api/conversions/{conversion}/cancel', [ConversionController::class, 'cancel'])->where('conversion', '[a-f0-9]{64}')->name('conversions.cancel');
Route::get('/prepared/{conversion}', [ConversionController::class, 'stream'])->where('conversion', '[a-f0-9]{64}')->name('conversions.stream');
