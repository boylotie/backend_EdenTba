<?php

use App\Shared\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

it('formats une enveloppe de succès', function () {
    $response = ApiResponse::success(['id' => 1], ['custom' => true]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))
        ->toMatchArray([
            'data' => ['id' => 1],
            'meta' => ['custom' => true],
            'error' => null,
        ]);
});

it('formats une enveloppe d erreur avec détails', function () {
    $response = ApiResponse::error(
        'validation_error',
        'Les données envoyées sont invalides.',
        422,
        ['email' => ['L adresse email est requise.']],
    );

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['error'])
        ->toMatchArray([
            'code' => 'validation_error',
            'message' => 'Les données envoyées sont invalides.',
            'details' => ['email' => ['L adresse email est requise.']],
        ]);
});

it('formats une enveloppe paginée', function () {
    $paginator = new LengthAwarePaginator(
        items: collect(['a', 'b']),
        total: 3,
        perPage: 2,
        currentPage: 1,
    );

    $data = ApiResponse::paginate($paginator)->getData(true);

    expect($data['meta']['pagination'])->toMatchArray([
        'current_page' => 1,
        'per_page' => 2,
        'total' => 3,
        'last_page' => 2,
        'first_item' => 1,
        'last_item' => 2,
        'has_more_pages' => true,
    ])
        ->and($data['error'])->toBeNull()
        ->and($data['data'])->toBe(['a', 'b']);
});
