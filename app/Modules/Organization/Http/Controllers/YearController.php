<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreYearRequest;
use App\Modules\Organization\Http\Requests\UpdateYearRequest;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\YearService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class YearController extends Controller
{
    public function __construct(private readonly YearService $years) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Year::class);

        return ApiResponse::success(['years' => Year::query()->orderBy('label')->get()]);
    }

    public function store(StoreYearRequest $request): JsonResponse
    {
        $this->authorize('create', Year::class);

        $data = [
            'label' => (string) $request->string('label'),
            'theme' => $request->filled('theme') ? (string) $request->string('theme') : null,
            'is_current' => $request->boolean('is_current'),
        ];

        $year = $this->years->create($data);

        return ApiResponse::success(['year' => $year], status: 201);
    }

    public function update(UpdateYearRequest $request, Year $year): JsonResponse
    {
        $this->authorize('update', $year);

        $data = [
            'label' => (string) $request->string('label'),
            'theme' => $request->filled('theme') ? (string) $request->string('theme') : null,
        ];

        if ($request->has('is_current')) {
            $data['is_current'] = $request->boolean('is_current');
        }

        $year = $this->years->update($year, $data);

        return ApiResponse::success(['year' => $year]);
    }

    public function destroy(Year $year): JsonResponse
    {
        $this->authorize('delete', $year);

        if (! $this->years->delete($year)) {
            return ApiResponse::error('year_in_use', 'Cette année est encore utilisée et ne peut pas être supprimée.', 422);
        }

        return ApiResponse::success(['message' => 'Année supprimée.']);
    }

    public function current(): JsonResponse
    {
        $this->authorize('viewAny', Year::class);

        $year = Year::query()->where('is_current', true)->first();

        if ($year === null) {
            return ApiResponse::error('year_not_found', "Aucune année courante n'est définie.", 404);
        }

        return ApiResponse::success(['year' => $year]);
    }
}
