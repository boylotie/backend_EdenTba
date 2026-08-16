<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreMonthRequest;
use App\Modules\Organization\Http\Requests\UpdateMonthRequest;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\MonthService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class MonthController extends Controller
{
    public function __construct(private readonly MonthService $months) {}

    public function index(Year $year): JsonResponse
    {
        $this->authorize('viewAny', Month::class);

        return ApiResponse::success([
            'months' => $year->months()->orderBy('month_number')->get(),
        ]);
    }

    public function store(Year $year, StoreMonthRequest $request): JsonResponse
    {
        $this->authorize('create', Month::class);

        $data = [
            'month_number' => (int) $request->input('month_number'),
            'theme' => $request->filled('theme') ? (string) $request->string('theme') : null,
        ];

        $month = $this->months->create($year, $data);

        return ApiResponse::success(['month' => $month], status: 201);
    }

    public function update(Year $year, Month $month, UpdateMonthRequest $request): JsonResponse
    {
        $this->authorize('update', $month);

        $data = [
            'month_number' => (int) $request->input('month_number'),
            'theme' => $request->filled('theme') ? (string) $request->string('theme') : null,
        ];

        $month = $this->months->update($month, $data);

        return ApiResponse::success(['month' => $month]);
    }

    public function destroy(Year $year, Month $month): JsonResponse
    {
        $this->authorize('delete', $month);

        if (! $this->months->delete($month)) {
            return ApiResponse::error('month_in_use', 'Ce mois est encore utilisé et ne peut pas être supprimé.', 422);
        }

        return ApiResponse::success(['message' => 'Mois supprimé.']);
    }
}
