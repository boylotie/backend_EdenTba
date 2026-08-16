<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Http\Requests\StatisticsIndexRequest;
use App\Modules\Analytics\Models\StatisticsReport;
use App\Modules\Analytics\Services\StatisticsService;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Consultation des statistiques d'écoute (MOD-12-P1, US-048).
 * Lecture réservée à la permission `statistics.view`.
 */
class StatisticsController extends Controller
{
    public function __construct(
        private readonly StatisticsService $statistics,
    ) {}

    public function index(StatisticsIndexRequest $request): JsonResponse
    {
        $this->authorize('view', StatisticsReport::class);

        $report = $this->statistics->report($request->period(), $request->limit());

        AuditLogger::log('statistics.view', [
            'period' => $request->period(),
            'limit' => $request->limit(),
        ]);

        return ApiResponse::success(['statistics' => $report]);
    }
}
