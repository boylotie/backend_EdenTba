<?php

it('renvoie l enveloppe standardisée sur l endpoint info', function () {
    $this->getJson('/api/v1')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['name', 'version', 'modules'],
            'meta',
            'error',
        ])
        ->assertJsonPath('error', null)
        ->assertJsonPath('data.version', 'v1');
});

it('renvoie une 404 normalisée sur une route inconnue', function () {
    $this->getJson('/api/v1/inexistante')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found')
        ->assertJsonPath('error.details', null);
});

it('renvoie une 405 normalisée sur une méthode non autorisée', function () {
    $this->postJson('/api/v1')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'method_not_allowed');
});

it('applique le rate limiting et renvoie une 429 normalisée', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1')->assertOk();
    }

    $this->getJson('/api/v1')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited');
});
