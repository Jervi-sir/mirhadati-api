<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/taxonomy', \App\Http\Controllers\Api\TaxonomyController::class);

Route::prefix('auth')->group(function () {
    Route::post('register', [\App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
    Route::post('login',    [\App\Http\Controllers\Api\Auth\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me',     [\App\Http\Controllers\Api\Auth\AuthController::class, 'me']);
        Route::post('logout', [\App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
        Route::put('profile', [\App\Http\Controllers\Api\Auth\ProfileController::class, 'update']); // ApiRoutes.ProfileUpdate
        Route::post('upgrade-to-host', \App\Http\Controllers\Api\Auth\UpgradeToHostController::class);
    });
});

// Public list & show
Route::prefix('toilets')->group(function () {
    Route::middleware('auth.optional')->group(function () {
        Route::get('/', \App\Http\Controllers\Api\Toilet\ToiletIndexController::class);
        Route::get('{toilet}', \App\Http\Controllers\Api\Toilet\ToiletShowController::class);
    });
});

Route::get('/toilets-markers', \App\Http\Controllers\Api\Toilet\ToiletMarkerIndexController::class);

// Favorites
Route::middleware('auth:sanctum')->middleware('auth.optional')->group(function () {
    Route::post('toilets/{toilet}/favorite',  [\App\Http\Controllers\Api\FavoriteController::class, 'store']);
    Route::delete('toilets/{toilet}/favorite', [\App\Http\Controllers\Api\FavoriteController::class, 'destroy']);
    Route::get('me/favorites',                [\App\Http\Controllers\Api\FavoriteController::class, 'index']);
});
// Sessions
Route::middleware('auth:sanctum')->group(function () {
    Route::post('toilets/{toilet}/sessions/start',            [\App\Http\Controllers\Api\ToiletSessionController::class, 'start']);
    Route::post('toilets/{toilet}/sessions/{sessionId}/end',  [\App\Http\Controllers\Api\ToiletSessionController::class, 'end']);
    Route::get('me/sessions',                                 [\App\Http\Controllers\Api\ToiletSessionController::class, 'mySessions']);
});

// Reviews
Route::middleware('auth.optional')->get('toilets/{toilet}/reviews',                        [\App\Http\Controllers\Api\ToiletReviewController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('toilets/{toilet}/reviews',                   [\App\Http\Controllers\Api\ToiletReviewController::class, 'store']);
    Route::patch('toilets/{toilet}/reviews/me',               [\App\Http\Controllers\Api\ToiletReviewController::class, 'updateMine']);
    Route::delete('toilets/{toilet}/reviews/me',              [\App\Http\Controllers\Api\ToiletReviewController::class, 'destroyMine']);
});

// Reports
Route::middleware('auth:sanctum')->group(function () {
    Route::post('toilets/{toilet}/reports',                   [\App\Http\Controllers\Api\ToiletReportController::class, 'store']);
    Route::get('toilets/{toilet}/reports',                    [\App\Http\Controllers\Api\ToiletReportController::class, 'index']);     // owner/admin
    Route::post('toilets/{toilet}/reports/{reportId}/resolve', [\App\Http\Controllers\Api\ToiletReportController::class, 'resolve']);  // owner/admin
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/uploads/toilet-photo', [App\Http\Controllers\Api\Upload\UploadController::class, 'toiletPhoto']); // multipart
});


/* ----------------------- Host ----------------------- */

Route::prefix('host')->middleware(['auth:sanctum'])->group(function () {
    Route::get('me', \App\Http\Controllers\Api\Host\HostMeController::class);
    Route::get('toilets', \App\Http\Controllers\Api\Host\HostToiletIndexController::class);

    Route::prefix('toilets')->group(function () {
        Route::get('{toilet}', [\App\Http\Controllers\Api\Host\HostToiletShowController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\Toilet\ToiletMutateController::class, 'store']);
        Route::match(['put', 'patch'], '{toilet}', [\App\Http\Controllers\Api\Toilet\ToiletMutateController::class, 'update']);
        Route::delete('{toilet}', [\App\Http\Controllers\Api\ToiletController::class, 'destroy']);
        Route::post('{toilet}/status', [\App\Http\Controllers\Api\ToiletController::class, 'setStatus']);
    });
});
