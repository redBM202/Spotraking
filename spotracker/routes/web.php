<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpotifyController;

Route::get('/', function () {
    return view('welcome');
});

// Move callback route outside prefix group
Route::get('/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

Route::prefix('spotify')->group(function () {
    Route::get('/login', [SpotifyController::class, 'login'])->name('spotify.login');
    Route::get('/search', [SpotifyController::class, 'search'])->name('spotify.search');
    Route::get('/player', [SpotifyController::class, 'player'])->name('spotify.player');
    Route::post('/play', [SpotifyController::class, 'play'])->name('spotify.play');
    Route::post('/pause', [SpotifyController::class, 'pause'])->name('spotify.pause');
    Route::post('/next', [SpotifyController::class, 'next'])->name('spotify.next');
    Route::post('/previous', [SpotifyController::class, 'previous'])->name('spotify.previous');
    Route::get('/webplayer', [SpotifyController::class, 'webplayer'])->name('spotify.webplayer');
    Route::get('/devices', [SpotifyController::class, 'getDevices'])->name('spotify.devices');
    Route::get('/playback-state', [SpotifyController::class, 'getPlaybackState'])->name('spotify.playback-state');
});
