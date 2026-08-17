<?php

use App\Modules\Streaming\Http\Controllers\AdminLiveStreamController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('admin/roles', 'pages::admin.roles')
        ->name('admin.roles.index')
        ->middleware('permission:roles.view');

    Route::livewire('admin/audit-logs', 'pages::admin.audit-logs')
        ->name('admin.audit-logs.index')
        ->middleware('permission:audit.view');

    Route::livewire('admin/contents', 'pages::admin.contents')
        ->name('admin.contents.index')
        ->middleware('permission:content.view');

    Route::livewire('admin/settings', 'pages::admin.settings')
        ->name('admin.settings.index')
        ->middleware('permission:settings.manage');

    Route::livewire('admin/years', 'pages::admin.years')
        ->name('admin.years.index')
        ->middleware('permission:schedule.manage');

    Route::livewire('admin/years/{year}/months', 'pages::admin.months')
        ->name('admin.years.months.index')
        ->middleware('permission:schedule.manage');

    Route::livewire('admin/years/{year}/weeks', 'pages::admin.weeks')
        ->name('admin.years.weeks.index')
        ->middleware('permission:schedule.manage');

    Route::livewire('admin/years/{year}/weeks/{week}/programs', 'pages::admin.programs')
        ->name('admin.years.weeks.programs.index')
        ->scopeBindings()
        ->middleware('permission:schedule.manage');

    Route::livewire('admin/activity-types', 'pages::admin.activity-types')
        ->name('admin.activity-types.index')
        ->middleware('permission:special_activity.manage');

    Route::livewire('admin/special-activities', 'pages::admin.special-activities')
        ->name('admin.special-activities.index')
        ->middleware('permission:special_activity.manage');

    Route::livewire('admin/special-activities/{activity}', 'pages::admin.special-activity-sessions')
        ->name('admin.special-activities.show')
        ->middleware('permission:special_activity.manage');

    Route::livewire('admin/speakers', 'pages::admin.speakers')
        ->name('admin.speakers.index')
        ->middleware('permission:speaker.view');

    Route::livewire('admin/live', 'pages::admin.live')
        ->name('admin.live.index')
        ->middleware('permission:streaming.start');

    Route::post('admin/live/stream-chunk', [AdminLiveStreamController::class, 'chunk'])
        ->name('admin.live.stream.chunk')
        ->middleware('permission:streaming.start');

    Route::post('admin/live/stream-stop', [AdminLiveStreamController::class, 'stop'])
        ->name('admin.live.stream.stop')
        ->middleware('permission:streaming.start');
});

require __DIR__.'/settings.php';
