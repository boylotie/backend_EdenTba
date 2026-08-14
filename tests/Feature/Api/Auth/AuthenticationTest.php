<?php

use App\Models\User;

it('connecte un utilisateur et retourne un token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']])
        ->assertJsonPath('error', null);
});

it('refuse des identifiants incorrects', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'incorrect',
    ])->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('refuse la connexion d un compte désactivé', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(403)
        ->assertJsonPath('error.code', 'account_disabled');
});

it('retourne le profil via /me avec un token valide', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('error', null);
});

it('refuse /me sans token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('révoque le token au logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile');
    $tokenId = $token->accessToken->id;

    $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertOk()
        ->assertJsonPath('error', null);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

it('refuse un token révoqué', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile');

    $token->accessToken->delete();

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});
