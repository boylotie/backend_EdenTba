<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('demande un lien de réinitialisation sans révéler l existence du compte', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'inconnu@exemple.org'])
        ->assertOk()
        ->assertJsonPath('error', null);

    Notification::assertNothingSent();
});

it('envoie un lien de réinitialisation à un compte existant', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('réinitialise le mot de passe avec un jeton valide et révoque les tokens', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $token) {
        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'nouveau-mdp',
            'password_confirmation' => 'nouveau-mdp',
        ])->assertOk()
            ->assertJsonPath('error', null);

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);

        return true;
    });

    expect(Hash::check('nouveau-mdp', $user->fresh()->password))->toBeTrue();
});

it('refuse un jeton de réinitialisation invalide', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'invalide',
        'email' => $user->email,
        'password' => 'nouveau-mdp',
        'password_confirmation' => 'nouveau-mdp',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_token');
});

it('exige l ancien mot de passe pour changer le mot de passe connecté', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'incorrect',
        'password' => 'nouveau-mdp',
        'password_confirmation' => 'nouveau-mdp',
    ], ['Authorization' => 'Bearer '.$token])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['current_password']]]);
});

it('change le mot de passe et révoque les autres tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile');
    $otherToken = $user->createToken('mobile-autre');

    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mdp',
        'password_confirmation' => 'nouveau-mdp',
    ], ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertOk()
        ->assertJsonPath('error', null);

    expect(Hash::check('nouveau-mdp', $user->fresh()->password))->toBeTrue();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
});

it('refuse un token révoqué après réinitialisation', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $user->tokens()->delete();

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(401);
});
