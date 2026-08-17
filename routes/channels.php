<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Les channels WebSocket pour le temps réel. Chaque channel est soit
| public (accessible à tous), soit privé (vérification d'authentification).
|
*/

// Live stream status — public, anyone can listen
Broadcast::channel('live.status', function () {
    Log::info('[Reverb Backend] Channel authorization: live.status (public)');

    return true;
});

// Admin content management — private, requires content.view permission
Broadcast::channel('admin.contents', function (User $user) {
    $granted = $user->hasPermission('content.view');
    Log::info('[Reverb Backend] Channel authorization: admin.contents', [
        'user_id' => $user->id,
        'granted' => $granted,
    ]);

    return $granted;
});

// Admin speaker management — private, requires speaker.view permission
Broadcast::channel('admin.speakers', function (User $user) {
    $granted = $user->hasPermission('speaker.view');
    Log::info('[Reverb Backend] Channel authorization: admin.speakers', [
        'user_id' => $user->id,
        'granted' => $granted,
    ]);

    return $granted;
});

// User personal notifications — private, only the user themselves
Broadcast::channel('user.{id}', function (User $user, int $id) {
    $granted = $user->id === $id;
    Log::info('[Reverb Backend] Channel authorization: user.{id}', [
        'user_id' => $user->id,
        'requested_id' => $id,
        'granted' => $granted,
    ]);

    return $granted;
});
