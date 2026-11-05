<?php

use FlorianBerger\RestApi\Http\Controllers\LeagueController;
use FlorianBerger\RestApi\Http\Controllers\MatchdayController;
use FlorianBerger\RestApi\Http\Controllers\RankingController;
use FlorianBerger\RestApi\Http\Controllers\TipController;
use Illuminate\Support\Facades\Route;

// No middleware group "web": the API is stateless (no session, no cookie, no CSRF). The key in the "Authorization"
// header identifies the user, see AuthenticateApiKey.
//
// "rest-api.page:..." makes an endpoint available only, when the page of the website with the same content is visible
// for the user (internal types of the pages, see PageHelper::defaultPageSettings()). It must come after "rest-api.auth".
Route::middleware(['rest-api.json', 'rest-api.auth'])->prefix('api/v1')->group(function () {
    // Leagues and seasons (only the available leagues, needed for every other call)
    Route::get('leagues', [LeagueController::class, 'index'])->name('plugin-rest-api.api.leagues');
    Route::get('leagues/{league}/seasons', [LeagueController::class, 'seasons'])->where('league', '[0-9]{1,15}')->name('plugin-rest-api.api.seasons');

    // Matchdays/rounds: needed by the tip page, the tip overview and the ranking filter
    Route::middleware('rest-api.page:tip-games,tip-overview,ranking-list-single')->group(function () {
        Route::get('seasons/{season}/matchdays', [MatchdayController::class, 'index'])->where('season', '[0-9]{1,15}')->name('plugin-rest-api.api.matchdays');
        Route::get('seasons/{season}/matchdays/{matchday}', [MatchdayController::class, 'show'])->where('season', '[0-9]{1,15}')->name('plugin-rest-api.api.matchday');
    });

    // Own tips and tip overview
    Route::get('seasons/{season}/matchdays/{matchday}/tips', [MatchdayController::class, 'tips'])->where('season', '[0-9]{1,15}')->middleware('rest-api.page:tip-games')->name('plugin-rest-api.api.tips');
    Route::get('seasons/{season}/matchdays/{matchday}/overview', [MatchdayController::class, 'overview'])->where('season', '[0-9]{1,15}')->middleware('rest-api.page:tip-overview')->name('plugin-rest-api.api.overview');

    // Rankings
    Route::get('seasons/{season}/ranking', [RankingController::class, 'bySeason'])->where('season', '[0-9]{1,15}')->middleware('rest-api.page:ranking-list-single')->name('plugin-rest-api.api.ranking');
    Route::get('leagues/{league}/ranking/all-time', [RankingController::class, 'allTime'])->where('league', '[0-9]{1,15}')->middleware('rest-api.page:ranking-list-all-time')->name('plugin-rest-api.api.ranking-all-time');

    // Change the own tip: PUT creates or changes, DELETE removes
    Route::middleware('rest-api.page:tip-games')->group(function () {
        Route::put('games/{game}/tip', [TipController::class, 'update'])->where('game', '[0-9]{1,15}')->name('plugin-rest-api.api.tip.update');
        Route::delete('games/{game}/tip', [TipController::class, 'destroy'])->where('game', '[0-9]{1,15}')->name('plugin-rest-api.api.tip.delete');
    });

    // Everything else below /api/v1 answers with JSON, not with the page of the website (xTIPP has a fallback route)
    Route::any('{path}', fn () => response()->json(['message' => 'Not found.'], 404))->where('path', '.*')->name('plugin-rest-api.api.not-found');
});
