<?php
use App\Http\Controllers\GameController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('lobby');
});

Route::get('/game/create', [GameController::class, 'create'])->name('game.create');
Route::get('/game/{uuid}', [GameController::class, 'show'])->name('game.show');
Route::get('/game/{uuid}/state', [GameController::class, 'state'])->name('game.state');
Route::post('/game/{uuid}/join', [GameController::class, 'join'])->name('game.join');
Route::post('/game/{uuid}/move', [GameController::class, 'makeMove'])->name('game.move');
Route::post('/game/{uuid}/reset', [GameController::class, 'reset'])->name('game.reset');
