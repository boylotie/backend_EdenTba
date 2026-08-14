<?php

use App\Shared\Api\ApiExceptionRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('convertit une erreur de validation en 422', function () {
    $exception = ValidationException::withMessages(['email' => ['Requis.']]);

    $response = ApiExceptionRenderer::render($exception, Request::create('/api/v1/content'));

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['error']['code'])->toBe('validation_error')
        ->and($response->getData(true)['error']['details']['email'])->toBe(['Requis.']);
});

it('convertit une erreur d authentification en 401', function () {
    $response = ApiExceptionRenderer::render(
        new AuthenticationException,
        Request::create('/api/v1/content'),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['error']['code'])->toBe('unauthenticated');
});

it('convertit une erreur d autorisation en 403', function () {
    $response = ApiExceptionRenderer::render(
        new AuthorizationException,
        Request::create('/api/v1/content'),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error']['code'])->toBe('forbidden');
});

it('convertit une ressource introuvable en 404', function () {
    $response = ApiExceptionRenderer::render(
        new NotFoundHttpException,
        Request::create('/api/v1/content/999'),
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error']['code'])->toBe('not_found');
});

it('convertit un modèle introuvable en 404', function () {
    $response = ApiExceptionRenderer::render(
        new ModelNotFoundException,
        Request::create('/api/v1/content/999'),
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error']['code'])->toBe('not_found');
});

it('convertit le rate limiting en 429 avec en-tête de réessai', function () {
    $exception = new ThrottleRequestsException('Too Many Requests', headers: ['Retry-After' => 60]);

    $response = ApiExceptionRenderer::render($exception, Request::create('/api/v1/content'));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('60')
        ->and($response->getData(true)['error']['code'])->toBe('rate_limited');
});

it('convertit une erreur interne sans fuite technique en production', function () {
    config(['app.debug' => false]);

    $response = ApiExceptionRenderer::render(
        new RuntimeException('secret detail technique'),
        Request::create('/api/v1/content'),
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['error']['code'])->toBe('internal_error')
        ->and($response->getData(true)['error']['message'])->toBe('Erreur interne du serveur.')
        ->and($response->getContent())->not->toContain('secret detail technique');
});

it('expose le message technique en mode debug', function () {
    config(['app.debug' => true]);

    $response = ApiExceptionRenderer::render(
        new RuntimeException('detail technique local'),
        Request::create('/api/v1/content'),
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['error']['message'])->toBe('detail technique local');
});

it('ne touche pas aux requêtes hors API', function () {
    $response = ApiExceptionRenderer::render(
        new RuntimeException,
        Request::create('/login'),
    );

    expect($response)->toBeNull();
});
