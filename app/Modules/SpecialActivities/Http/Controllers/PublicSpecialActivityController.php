<?php

namespace App\Modules\SpecialActivities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Support\SpecialActivityPublicCache;
use App\Shared\Api\ApiResponse;
use App\Shared\Support\PaginatedCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PublicSpecialActivityController extends Controller
{
    private const CACHE_TTL = 300;

    /**
     * Lecture publique (D-02) : aucune notion de publication n'existe encore
     * sur les activités (les statuts arrivent avec MOD-06) ; le filtrage
     * « publié » (A1) sera activé à ce moment-là.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);

        $paginator = PaginatedCache::remember(
            'public.special-activities.v'.SpecialActivityPublicCache::version().'.page.'.$page,
            self::CACHE_TTL,
            fn (): LengthAwarePaginator => SpecialActivity::query()
                ->withCount('sessions')
                ->with(['week:id,label,year_id', 'activityType:id,code,label'])
                ->orderByDesc('id')
                ->paginate(10, ['id', 'week_id', 'activity_type_id', 'name', 'mode', 'starts_on', 'ends_on', 'created_at', 'updated_at'], 'page', $page),
        );

        $response = ApiResponse::paginate($paginator);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }

    public function show(SpecialActivity $activity): JsonResponse
    {
        $data = Cache::remember(
            'public.special-activities.v'.SpecialActivityPublicCache::version().'.'.$activity->id,
            self::CACHE_TTL,
            function () use ($activity): array {
                return [
                    'special_activity' => [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'mode' => $activity->mode,
                        'starts_on' => $activity->starts_on,
                        'ends_on' => $activity->ends_on,
                        'week' => [
                            'id' => $activity->week_id,
                            'label' => $activity->week->label,
                            'year_id' => $activity->week->year_id,
                        ],
                        'activity_type' => [
                            'id' => $activity->activity_type_id,
                            'code' => $activity->activityType->code,
                            'label' => $activity->activityType->label,
                        ],
                        'sessions' => $activity->sessions()
                            ->orderBy('day_of_week')
                            ->orderBy('start_time')
                            ->get(['id', 'day_of_week', 'start_time', 'duration_minutes', 'place', 'theme']),
                    ],
                ];
            },
        );

        $response = ApiResponse::success($data);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }
}
