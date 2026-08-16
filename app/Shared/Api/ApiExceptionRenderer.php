<?php

namespace App\Shared\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    /**
     * Convertit n'importe quelle exception en enveloppe d'erreur API.
     *
     * Retourne null hors périmètre API afin de laisser le handler par défaut
     * (web) traiter la requête. L'exception reste journalisée par Laravel.
     */
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => ApiResponse::error(
                'validation_error',
                __('Les données envoyées sont invalides.'),
                422,
                $exception->errors(),
            ),

            $exception instanceof ThrottleRequestsException => ApiResponse::error(
                'rate_limited',
                __('Trop de requêtes. Réessayez plus tard.'),
                429,
                headers: $exception->getHeaders(),
            ),

            $exception instanceof AuthenticationException => ApiResponse::error(
                'unauthenticated',
                __('Vous devez être authentifié.'),
                401,
            ),

            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => ApiResponse::error(
                'forbidden',
                __('Accès refusé.'),
                403,
            ),

            $exception instanceof MethodNotAllowedHttpException => ApiResponse::error(
                'method_not_allowed',
                __('Méthode non autorisée.'),
                405,
            ),

            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => ApiResponse::error(
                'not_found',
                __('Ressource introuvable.'),
                404,
            ),

            $exception instanceof HttpExceptionInterface => ApiResponse::error(
                'http_error',
                $exception->getMessage() !== '' ? $exception->getMessage() : __('Erreur.'),
                $exception->getStatusCode(),
            ),

            $exception instanceof QueryException => self::internalError($exception),

            default => self::internalError($exception),
        };
    }

    private static function internalError(Throwable $exception): JsonResponse
    {
        $message = app()->hasDebugModeEnabled()
            ? $exception->getMessage()
            : __('Erreur interne du serveur.');

        return ApiResponse::error('internal_error', $message, 500);
    }
}
