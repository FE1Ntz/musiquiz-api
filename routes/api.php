<?php

use App\Http\Controllers\ArtistImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeezerArtistController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('admin')->group(function (): void {
    Route::get('/deezer/artists', [DeezerArtistController::class, 'search'])->name('admin.deezer.artists.search');
    Route::get('/deezer/artists/{deezerArtistId}', [DeezerArtistController::class, 'show'])->name('admin.deezer.artists.show');
    Route::post('/artists/import', [ArtistImportController::class, 'store'])->name('admin.artists.import');
});
