<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

it('crée un compte et retourne un token', function () {
    Log::spy();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jean',
        'email' => 'jean@exemple.org',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['user' => ['id', 'name', 'email'], 'token'],
            'meta',
            'error',
        ])
        ->assertJsonPath('error', null);

    $this->assertDatabaseHas('users', ['email' => 'jean@exemple.org']);

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message) => $message === 'auth.register');
});

it('renvoie une 422 pour un e-mail déjà utilisé', function () {
    User::factory()->create(['email' => 'jean@exemple.org']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jean',
        'email' => 'jean@exemple.org',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['email']]]);
});

it('renvoie une 422 si les mots de passe ne correspondent pas', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jean',
        'email' => 'jean@exemple.org',
        'password' => 'password',
        'password_confirmation' => 'autre-mot-de-passe',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});
