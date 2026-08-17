<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\NewPasswordController;
use App\Http\Controllers\Api\Auth\PasswordUpdateController;
use App\Http\Controllers\Api\Auth\RegisteredUserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReminderSettingsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserRoleController;
use App\Modules\Analytics\Http\Controllers\ListeningEventController;
use App\Modules\Analytics\Http\Controllers\StatisticsController;
use App\Modules\Content\Http\Controllers\ContentController;
use App\Modules\Content\Http\Controllers\MeFavoriteController;
use App\Modules\Content\Http\Controllers\MeHistoryController;
use App\Modules\Notifications\Http\Controllers\AdminNotificationController;
use App\Modules\Notifications\Http\Controllers\DeviceController;
use App\Modules\Notifications\Http\Controllers\MeNotificationController;
use App\Modules\Notifications\Http\Controllers\MeNotificationPreferenceController;
use App\Modules\Organization\Http\Controllers\MonthController;
use App\Modules\Organization\Http\Controllers\ProgramController;
use App\Modules\Organization\Http\Controllers\PublicNavigationController;
use App\Modules\Organization\Http\Controllers\WeekController;
use App\Modules\Organization\Http\Controllers\YearController;
use App\Modules\Playlists\Http\Controllers\PlaylistController;
use App\Modules\Playlists\Http\Controllers\PublicPlaylistController;
use App\Modules\Speakers\Http\Controllers\SpeakerController;
use App\Modules\SpecialActivities\Http\Controllers\ActivityTypeController;
use App\Modules\SpecialActivities\Http\Controllers\PublicSpecialActivityController;
use App\Modules\SpecialActivities\Http\Controllers\SpecialActivityController;
use App\Modules\Streaming\Http\Controllers\LiveController;
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
            'content' => '/api/v1/contents',
            'playlists' => '/api/v1/playlists',
            'notifications' => '/api/v1/notifications',
            'streaming' => '/api/v1/live',
            'analytics' => '/api/v1/admin/statistics',
            'speakers' => '/api/v1/speakers',
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

// MOD-01 — RBAC (US-007, US-008, US-009)
Route::middleware('auth:sanctum')->prefix('roles')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware('auth:sanctum')->prefix('permissions')->group(function (): void {
    Route::get('/', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/', [PermissionController::class, 'store'])->name('permissions.store');
    Route::put('/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
});

Route::middleware('auth:sanctum')->prefix('users')->group(function (): void {
    Route::put('/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
});

// MOD-02 — Audit (US-010)
Route::middleware('auth:sanctum')->prefix('audit-logs')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

// MOD-02 — Paramètres système (US-012)
Route::middleware('auth:sanctum')->prefix('settings')->group(function (): void {
    Route::get('/', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/reminders', [ReminderSettingsController::class, 'index'])->name('settings.reminders.index');
    Route::put('/reminders', [ReminderSettingsController::class, 'update'])->name('settings.reminders.update');
});

// MOD-03 — Organization (années & thèmes annuels — US-013, mois & thèmes mensuels — US-014)
Route::middleware('auth:sanctum')->prefix('years')->group(function (): void {
    Route::get('/', [YearController::class, 'index'])->name('years.index');
    Route::get('/current', [YearController::class, 'current'])->name('years.current');
    Route::post('/', [YearController::class, 'store'])->name('years.store');
    Route::put('/{year}', [YearController::class, 'update'])->name('years.update');
    Route::delete('/{year}', [YearController::class, 'destroy'])->name('years.destroy');

    Route::prefix('{year}/months')->scopeBindings()->group(function (): void {
        Route::get('/', [MonthController::class, 'index'])->name('years.months.index');
        Route::post('/', [MonthController::class, 'store'])->name('years.months.store');
        Route::put('/{month}', [MonthController::class, 'update'])->name('years.months.update');
        Route::delete('/{month}', [MonthController::class, 'destroy'])->name('years.months.destroy');
    });

    Route::prefix('{year}/weeks')->scopeBindings()->group(function (): void {
        Route::get('/', [WeekController::class, 'index'])->name('years.weeks.index');
        Route::post('/', [WeekController::class, 'store'])->name('years.weeks.store');
        Route::put('/{week}', [WeekController::class, 'update'])->name('years.weeks.update');
        Route::delete('/{week}', [WeekController::class, 'destroy'])->name('years.weeks.destroy');
    });
});

// MOD-03 — Organization (programmes réguliers — US-016)
Route::middleware('auth:sanctum')->prefix('weeks/{week}/programs')->scopeBindings()->group(function (): void {
    Route::get('/', [ProgramController::class, 'index'])->name('weeks.programs.index');
    Route::post('/', [ProgramController::class, 'store'])->name('weeks.programs.store');
    Route::put('/{program}', [ProgramController::class, 'update'])->name('weeks.programs.update');
    Route::delete('/{program}', [ProgramController::class, 'destroy'])->name('weeks.programs.destroy');
});

// MOD-03 — Organization (API publique de navigation — US-017, lecture ouverte D-02)
Route::prefix('organization')->group(function (): void {
    Route::get('/years', [PublicNavigationController::class, 'years'])->name('public.organization.years');
    Route::get('/years/{year}', [PublicNavigationController::class, 'year'])->name('public.organization.years.show');
    Route::get('/years/{year}/months', [PublicNavigationController::class, 'months'])->name('public.organization.years.months');
    Route::get('/weeks/{week}/programs', [PublicNavigationController::class, 'programs'])->name('public.organization.weeks.programs');
});

// MOD-04 — SpecialActivities (API publique de lecture — US-020, D-02)
Route::prefix('special-activities')->group(function (): void {
    Route::get('/', [PublicSpecialActivityController::class, 'index'])->name('special-activities.public.index');
    Route::get('/{activity}', [PublicSpecialActivityController::class, 'show'])->name('special-activities.public.show');
});

// MOD-04 — SpecialActivities (activités & sessions — US-019)
Route::middleware('auth:sanctum')->prefix('special-activities')->group(function (): void {
    Route::post('/', [SpecialActivityController::class, 'store'])->name('special-activities.store');
    Route::put('/{activity}', [SpecialActivityController::class, 'update'])->name('special-activities.update');
    Route::delete('/{activity}', [SpecialActivityController::class, 'destroy'])->name('special-activities.destroy');

    Route::prefix('{activity}/sessions')->scopeBindings()->group(function (): void {
        Route::post('/', [SpecialActivityController::class, 'storeSession'])->name('special-activities.sessions.store');
        Route::put('/{session}', [SpecialActivityController::class, 'updateSession'])->name('special-activities.sessions.update');
        Route::delete('/{session}', [SpecialActivityController::class, 'destroySession'])->name('special-activities.sessions.destroy');
    });
});

// MOD-04 — SpecialActivities (types d'activités configurables — US-018)
Route::middleware('auth:sanctum')->prefix('activity-types')->group(function (): void {
    Route::get('/', [ActivityTypeController::class, 'index'])->name('activity-types.index');
    Route::post('/', [ActivityTypeController::class, 'store'])->name('activity-types.store');
    Route::put('/{activity_type}', [ActivityTypeController::class, 'update'])->name('activity-types.update');
    Route::delete('/{activity_type}', [ActivityTypeController::class, 'destroy'])->name('activity-types.destroy');
});

// MOD-05 — Content (contenus audio — US-021 upload, US-022 streaming, US-023 gestion, US-024 filtres)
Route::prefix('contents')->group(function (): void {
    Route::get('/', [ContentController::class, 'index'])->name('contents.public.index');
    Route::get('/{content}', [ContentController::class, 'show'])->name('contents.public.show');
    Route::get('/{content}/stream', [ContentController::class, 'stream'])->name('contents.stream');
    Route::get('/{content}/image', [ContentController::class, 'image'])->name('contents.image');
});

Route::middleware('auth:sanctum')->prefix('contents')->group(function (): void {
    Route::post('/upload', [ContentController::class, 'upload'])->name('contents.upload');
    Route::post('/', [ContentController::class, 'store'])->name('contents.store');
    Route::put('/{content}', [ContentController::class, 'update'])->name('contents.update');
    Route::put('/{content}/status', [ContentController::class, 'status'])->name('contents.status');
    Route::delete('/{content}', [ContentController::class, 'destroy'])->name('contents.destroy');
});

// MOD-08 — Playlists (API publique de lecture — US-036, D-02)
Route::prefix('playlists')->group(function (): void {
    Route::get('/', [PublicPlaylistController::class, 'index'])->name('playlists.public.index');
    Route::get('/{playlist}', [PublicPlaylistController::class, 'show'])->name('playlists.public.show');
});

// MOD-08 — Playlists (gestion — US-036)
Route::middleware('auth:sanctum')->prefix('playlists')->group(function (): void {
    Route::post('/', [PlaylistController::class, 'store'])->name('playlists.store');
    Route::put('/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
    Route::delete('/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');

    Route::prefix('{playlist}/items')->scopeBindings()->group(function (): void {
        Route::post('/', [PlaylistController::class, 'storeItem'])->name('playlists.items.store');
        Route::put('/', [PlaylistController::class, 'reorderItems'])->name('playlists.items.reorder');
        Route::delete('/{playlistItem}', [PlaylistController::class, 'destroyItem'])->name('playlists.items.destroy');
    });
});

// MOD-09 — Notifications internes (US-038) et push (tokens appareils — US-039)
Route::middleware('auth:sanctum')->prefix('me')->group(function (): void {
    Route::get('/notifications', [MeNotificationController::class, 'index'])->name('me.notifications.index');
    Route::put('/notifications/{notification}/read', [MeNotificationController::class, 'markAsRead'])->name('me.notifications.read');
    Route::post('/devices', [DeviceController::class, 'store'])->name('me.devices.store');
    Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->name('me.devices.destroy');
});

// MOD-09-P4 — Préférences de notification par type (US-041)
Route::middleware('auth:sanctum')->prefix('me')->group(function (): void {
    Route::get('/notification-preferences', [MeNotificationPreferenceController::class, 'index'])->name('me.notification-preferences.index');
    Route::put('/notification-preferences', [MeNotificationPreferenceController::class, 'update'])->name('me.notification-preferences.update');
});

// MOD-07-P5 — Favoris (US-034) et historique d'écoute (US-035) de l'utilisateur
Route::middleware('auth:sanctum')->prefix('me')->group(function (): void {
    Route::get('/favorites', [MeFavoriteController::class, 'index'])->name('me.favorites.index');
    Route::post('/favorites', [MeFavoriteController::class, 'store'])->name('me.favorites.store');
    Route::delete('/favorites/{content}', [MeFavoriteController::class, 'destroy'])->name('me.favorites.destroy');

    Route::get('/history', [MeHistoryController::class, 'index'])->name('me.history.index');
    Route::put('/history/{content}', [MeHistoryController::class, 'update'])->name('me.history.update');
});

// MOD-09-P3 — Notifications (envoi manuel et programmation — US-040)
Route::middleware('auth:sanctum')->prefix('notifications')->group(function (): void {
    Route::post('/send', [AdminNotificationController::class, 'send'])->name('notifications.send');
    Route::post('/schedule', [AdminNotificationController::class, 'schedule'])->name('notifications.schedule');
});

// MOD-11-P2 — Streaming : état du live (public), démarrage et arrêt (US-046)
Route::prefix('live')->group(function (): void {
    Route::get('/status', [LiveController::class, 'status'])->name('live.status');
    Route::get('/image', [LiveController::class, 'image'])->name('live.image');
});

Route::middleware('auth:sanctum')->prefix('live')->group(function (): void {
    Route::post('/start', [LiveController::class, 'start'])->name('live.start');
    Route::post('/stop', [LiveController::class, 'stop'])->name('live.stop');
});

// MOD-12 — Analytics (statistiques d'écoute — US-048)
Route::post('/listening-events', [ListeningEventController::class, 'store'])->name('listening-events.store');

Route::middleware('auth:sanctum')->prefix('admin/statistics')->group(function (): void {
    Route::get('/', [StatisticsController::class, 'index'])->name('admin.statistics.index');
});

// Speakers (pasteurs / conférenciers)
Route::prefix('speakers')->group(function (): void {
    Route::get('/', [SpeakerController::class, 'index'])->name('speakers.public.index');
    Route::get('/{speaker}', [SpeakerController::class, 'show'])->name('speakers.public.show');
});

Route::middleware('auth:sanctum')->prefix('speakers')->group(function (): void {
    Route::post('/', [SpeakerController::class, 'store'])->name('speakers.store');
    Route::put('/{speaker}', [SpeakerController::class, 'update'])->name('speakers.update');
    Route::delete('/{speaker}', [SpeakerController::class, 'destroy'])->name('speakers.destroy');
});
