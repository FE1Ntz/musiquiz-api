<?php

use App\Http\Controllers\ArtistImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeezerArtistController;
use App\Http\Controllers\GameAnswerController;
use App\Http\Controllers\MultiplayerGameController;
use App\Http\Controllers\PublicArtistController;
use App\Http\Controllers\SinglePlayerGameController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});

Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('admin')->group(function (): void {
    Route::get('/deezer/artists', [DeezerArtistController::class, 'search'])->name('admin.deezer.artists.search');
    Route::get('/deezer/artists/{deezerArtistId}', [DeezerArtistController::class, 'show'])->name('admin.deezer.artists.show');
    Route::post('/artists/import', [ArtistImportController::class, 'store'])->name('admin.artists.import');
});

// Public artist routes (no authentication required)
Route::get('/artists', [PublicArtistController::class, 'index'])->name('artists.index');
Route::get('/artists/{artist}', [PublicArtistController::class, 'show'])->name('artists.show');

// Game routes
Route::prefix('games')->group(function (): void {
    Route::post('/single-player', [SinglePlayerGameController::class, 'store'])->name('games.single-player.store');
    Route::get('/{gameSession}', [SinglePlayerGameController::class, 'show'])->name('games.show');
    Route::get('/{gameSession}/state', [SinglePlayerGameController::class, 'state'])->name('games.state');
    Route::post('/{gameSession}/next-round', [SinglePlayerGameController::class, 'nextRound'])->name('games.next-round');
    Route::post('/{gameSession}/answer', [GameAnswerController::class, 'store'])->name('games.answer');
    Route::post('/{gameSession}/timeout', [GameAnswerController::class, 'timeout'])->name('games.timeout');
    Route::post('/{gameSession}/finish', [SinglePlayerGameController::class, 'finish'])->name('games.finish');
});

// Multiplayer routes
Route::prefix('multiplayer')->group(function (): void {
    Route::post('/rooms', [MultiplayerGameController::class, 'createRoom'])->name('multiplayer.rooms.store');
    Route::post('/rooms/{code}/join', [MultiplayerGameController::class, 'joinRoom'])->name('multiplayer.rooms.join');
    Route::get('/rooms/{gameRoom}', [MultiplayerGameController::class, 'showRoom'])->name('multiplayer.rooms.show');
    Route::post('/rooms/{gameRoom}/leave', [MultiplayerGameController::class, 'leaveRoom'])->name('multiplayer.rooms.leave');
    Route::post('/rooms/{gameRoom}/start', [MultiplayerGameController::class, 'startGame'])->name('multiplayer.rooms.start');
    Route::post('/rooms/{gameRoom}/answer', [MultiplayerGameController::class, 'submitAnswer'])->name('multiplayer.rooms.answer');
    Route::post('/rooms/{gameRoom}/timeout', [MultiplayerGameController::class, 'timeout'])->name('multiplayer.rooms.timeout');
    Route::get('/rooms/{gameRoom}/leaderboard', [MultiplayerGameController::class, 'leaderboard'])->name('multiplayer.rooms.leaderboard');
});
