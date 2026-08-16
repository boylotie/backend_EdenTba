<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreWeekRequest;
use App\Modules\Organization\Http\Requests\UpdateWeekRequest;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\WeekService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class WeekController extends Controller
{
    public function __construct(private readonly WeekService $weeks) {}

    public function index(Year $year): JsonResponse
    {
        $this->authorize('viewAny', Week::class);

        return ApiResponse::success([
            'weeks' => $year->weeks()->orderBy('label')->get(),
        ]);
    }

    public function store(Year $year, StoreWeekRequest $request): JsonResponse
    {
        $this->authorize('create', Week::class);

        $data = [
            'label' => (string) $request->string('label'),
        ];

        $week = $this->weeks->create($year, $data);

        return ApiResponse::success(['week' => $week], status: 201);
    }

    public function update(Year $year, Week $week, UpdateWeekRequest $request): JsonResponse
    {
        $this->authorize('update', $week);

        $data = [
            'label' => (string) $request->string('label'),
        ];

        $week = $this->weeks->update($week, $data);

        return ApiResponse::success(['week' => $week]);
    }

    public function destroy(Year $year, Week $week): JsonResponse
    {
        $this->authorize('delete', $week);

        if (! $this->weeks->delete($week)) {
            return ApiResponse::error('week_in_use', 'Cette semaine est encore utilisée et ne peut pas être supprimée.', 422);
        }

        return ApiResponse::success(['message' => 'Semaine supprimée.']);
    }
}
