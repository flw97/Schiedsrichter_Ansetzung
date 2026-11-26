<?php

use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\MyGamesController;
use App\Http\Controllers\Api\NuLigaController;
use App\Http\Controllers\Api\RefereeController;
use App\Http\Controllers\Api\RulebookController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::connection()->getPdo();

    return ['status' => 'ok'];
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Rechteprüfung bei jeder Aktion über Gates (siehe App\Support\Permissions)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/state', [StateController::class, 'show']);
    Route::get('/notifications', [StateController::class, 'notifications']);
    Route::post('/games/{game}/report', [MyGamesController::class, 'report']);

    Route::middleware('can:ansetzen')->group(function () {
        Route::post('/games', [GameController::class, 'store']);
        Route::post('/games/{game}/selection', [GameController::class, 'selection']);
        Route::post('/games/{game}/confirm', [GameController::class, 'confirm']);
        Route::post('/games/{game}/undo', [GameController::class, 'undo']);
        Route::put('/games/{game}', [GameController::class, 'update']);
        Route::post('/nuliga/sync', [NuLigaController::class, 'sync']);
        Route::post('/nuliga/import-all', [NuLigaController::class, 'importAll']);
        Route::post('/nuliga/import/{game}', [NuLigaController::class, 'import']);
    });

    Route::middleware('can:saisonplanung')->group(function () {
        Route::post('/season-plan', [GameController::class, 'applySeasonPlan']);
        Route::put('/settings/abfrage', [MiscController::class, 'setAbfrage']);
    });

    Route::middleware('can:eigene')->group(function () {
        Route::put('/games/{game}/response', [MyGamesController::class, 'respond']);
        Route::post('/games/{game}/withdraw', [MyGamesController::class, 'withdraw']);
        Route::post('/games/{game}/reconfirm', [MyGamesController::class, 'reconfirm']);
        Route::put('/me/referee', [RefereeController::class, 'updateMe']);
    });

    Route::middleware('can:personen')->group(function () {
        Route::get('/referees/contacts', [RefereeController::class, 'contacts']);
        Route::post('/referees/sync', [RefereeController::class, 'sync']);
        Route::post('/referees/{referee}/sync', [RefereeController::class, 'sync']);
        Route::put('/referees/{referee}/license', [RefereeController::class, 'updateLicense']);
        Route::put('/referees/{referee}/teams', [RefereeController::class, 'updateTeams']);
        Route::post('/referees/{referee}/proofs', [RefereeController::class, 'addProof']);
        Route::post('/observations', [MiscController::class, 'addObservation']);
    });

    Route::get('/rulebooks/{rulebook}/file/{version?}', [RulebookController::class, 'file'])->middleware('can:regel_lesen');
    Route::post('/rulebooks/{rulebook}/read', [RulebookController::class, 'confirmRead'])->middleware('can:eigene');
    Route::middleware('can:regel_verwalten')->group(function () {
        Route::post('/rulebooks', [RulebookController::class, 'store']);
        Route::post('/rulebooks/{rulebook}/versions', [RulebookController::class, 'newVersion']);
        Route::delete('/rulebooks/{rulebook}', [RulebookController::class, 'destroy']);
    });

    Route::middleware('can:unterkunft_vorschlagen')->group(function () {
        Route::post('/accommodations', [AccommodationController::class, 'store']);
        Route::post('/accommodations/{accommodation}/reviews', [AccommodationController::class, 'review']);
    });
    Route::middleware('can:unterkunft_verwalten')->group(function () {
        Route::put('/accommodations/{accommodation}', [AccommodationController::class, 'update']);
        Route::delete('/accommodations/{accommodation}', [AccommodationController::class, 'destroy']);
    });

    Route::middleware('can:rollen')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
    });
});
