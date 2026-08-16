<?php

namespace App\Shared\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    /**
     * Enveloppe standardisée de réponse API.
     *
     * Succès : { "data": ..., "meta": ..., "error": null }
     * Erreur : { "data": null, "meta": null, "error": { "code", "message", "details" } }
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
            'error' => null,
        ], $status);
    }

    /**
     * @template TValue of mixed
     *
     * @param  LengthAwarePaginator<int, TValue>  $paginator
     * @param  array<string, mixed>|null  $extraMeta  métadonnées fusionnées après `pagination`
     */
    public static function paginate(LengthAwarePaginator $paginator, ?array $extraMeta = null): JsonResponse
    {
        return self::success(
            data: $paginator->items(),
            meta: array_merge([
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'first_item' => $paginator->firstItem(),
                    'last_item' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ], $extraMeta ?? []),
        );
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @param  array<string, mixed>  $headers
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'data' => null,
            'meta' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status, $headers);
    }
}
