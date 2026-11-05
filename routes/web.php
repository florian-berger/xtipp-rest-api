<?php

use FlorianBerger\RestApi\Http\Controllers\ApiKeyPageController;
use FlorianBerger\RestApi\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

// The middleware group "web" (session, cookies, CSRF) must be named, the routes of a plugin are outside of routes/web.php
Route::middleware(['web'])->group(function () {
    /**
     * Logged-in users: the own API key. There is no id in the addresses, it is always the key of the logged-in user.
     */
    Route::middleware(['auth', 'last_activity'])->prefix('api-schluessel')->group(function () {
        Route::get('/', [ApiKeyPageController::class, 'index'])->name('plugin-rest-api.keys');
        Route::post('/', [ApiKeyPageController::class, 'create'])->name('plugin-rest-api.keys.create');
        Route::post('neu-erzeugen', [ApiKeyPageController::class, 'regenerate'])->name('plugin-rest-api.keys.regenerate');
        Route::post('sperren', [ApiKeyPageController::class, 'revoke'])->name('plugin-rest-api.keys.revoke');
        Route::post('loeschen', [ApiKeyPageController::class, 'delete'])->name('plugin-rest-api.keys.delete');
    });

    /**
     * Logged-in users: Swagger UI, its description of the API and its CSS/JavaScript (the files of a plugin are not in
     * the web root, so they are delivered by a route)
     */
    Route::middleware(['auth', 'last_activity'])->prefix('api/docs')->group(function () {
        Route::get('/', [DocsController::class, 'index'])->name('plugin-rest-api.docs');
        Route::get('openapi.json', [DocsController::class, 'spec'])->name('plugin-rest-api.docs.spec');
        Route::get('assets/{file}', [DocsController::class, 'asset'])->name('plugin-rest-api.docs.asset');
    });
});
