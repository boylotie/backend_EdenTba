<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\NewPasswordController;
use App\Http\Controllers\Api\Auth\PasswordUpdateController;
use App\Http\Controllers\Api\Auth\RegisteredUserController;
use App\Shared\Api\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — préfixe /api/v1
|--------------------------------------------------------------------------
|
| Organisées par module métier. Chaque module ajoute ses routes dans sa
| section lors de sa propre phase (MOD-02 et suivants).
|
*/

Route::get('/', function () {
    return ApiResponse::success([
        'name' => config('app.name'),
        'version' => 'v1',
        'modules' => [
            'organization' => '/api/v1/organization',
            'special-activities' => '/api/v1/special-activities',
            'content' => '/api/v1/content',
            'playlists' => '/api/v1/playlists',
            'notifications' => '/api/v1/notifications',
            'streaming' => '/api/v1/streaming',
            'analytics' => '/api/v1/analytics',
        ],
    ]);
})->name('api.info');

// MOD-01 — Authentification API (US-004, US-005, US-006)
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('auth.register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('auth.login');
    Route::post('/password/forgot', [ForgotPasswordController::class, 'store'])->name('password.forgot');
    Route::post('/password/reset', [NewPasswordController::class, 'store'])->name('password.reset');
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
    Route::get('/me', [AuthenticatedSessionController::class, 'me'])->name('auth.me');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');
    Route::put('/password', [PasswordUpdateController::class, 'update'])->name('password.update');
});

// MOD-02 — Organization (organisation de l'église : années, mois, thèmes, activités)
// Route::prefix('organization')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Organization
// });

// MOD-03 — SpecialActivities (activités spéciales)
// Route::prefix('special-activities')->group(function (): void {
//     // endpoints ajoutés lors des phases du module SpecialActivities
// });

// MOD-04 — Content (contenus audio, statuts, métadonnées)
// Route::prefix('content')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Content
// });

// MOD-05 — Playlists (listes de lecture, programmation)
// Route::prefix('playlists')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Playlists
// });

// MOD-06 — Notifications (abonnements, envois, rappels)
// Route::prefix('notifications')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Notifications
// });

// MOD-11 — Streaming (streaming en direct)
// Route::prefix('streaming')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Streaming
// });

// MOD-12 — Analytics (statistiques et agrégats)
// Route::prefix('analytics')->group(function (): void {
//     // endpoints ajoutés lors des phases du module Analytics
// });
