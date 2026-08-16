<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

it('met à jour la dernière visite à la connexion', function () {
    $user = User::factory()->create();

    Auth::login($user);

    expect($user->refresh()->last_seen_at)->not->toBeNull();
});

it('met à jour la dernière visite lors du déclenchement de l événement Login', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    expect($user->refresh()->last_seen_at)->not->toBeNull();
});
