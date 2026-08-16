<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Shared\Api\ApiResponse;
use App\Shared\Support\PaginatedCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PublicNavigationController extends Controller
{
    private const CACHE_TTL = 300;

    /**
     * Endpoints publics de navigation (D-02) : lecture ouverte sans compte.
     *
     * Les données d'organisation sont structurelles : à ce stade aucune notion
     * de publication n'existe encore sur les années/mois/semaines/programmes
     * (les statuts arrivent avec MOD-06 et la sélection « publié » avec
     * MOD-04-P3). Le filtrage « publié » effectif est donc reporté.
     */
    public function years(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);

        $paginator = PaginatedCache::remember(
            'public.organization.v'.OrganizationPublicCache::version().'.years.page.'.$page,
            self::CACHE_TTL,
            fn (): LengthAwarePaginator => Year::query()
                ->orderByDesc('is_current')
                ->orderBy('label')
                ->paginate(10, ['id', 'label', 'theme', 'is_current', 'created_at', 'updated_at'], 'page', $page),
        );

        return $this->cacheable(ApiResponse::paginate($paginator));
    }

    public function year(Year $year): JsonResponse
    {
        $data = Cache::remember(
            'public.organization.v'.OrganizationPublicCache::version().'.year.'.$year->id,
            self::CACHE_TTL,
            function () use ($year): array {
                return [
                    'year' => [
                        'id' => $year->id,
                        'label' => $year->label,
                        'theme' => $year->theme,
                        'is_current' => $year->is_current,
                        'created_at' => $year->created_at,
                        'updated_at' => $year->updated_at,
                    ],
                    'months' => $year->months()
                        ->orderBy('month_number')
                        ->get(['id', 'month_number', 'theme']),
                    'weeks' => $year->weeks()
                        ->orderBy('label')
                        ->get(['id', 'label']),
                ];
            },
        );

        return $this->cacheable(ApiResponse::success($data));
    }

    public function months(Year $year, Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);

        $paginator = PaginatedCache::remember(
            'public.organization.v'.OrganizationPublicCache::version().'.year.'.$year->id.'.months.page.'.$page,
            self::CACHE_TTL,
            fn (): LengthAwarePaginator => $year->months()
                ->orderBy('month_number')
                ->paginate(10, ['id', 'month_number', 'theme', 'created_at', 'updated_at'], 'page', $page),
        );

        return $this->cacheable(ApiResponse::paginate($paginator));
    }

    public function programs(Week $week): JsonResponse
    {
        $data = Cache::remember(
            'public.organization.v'.OrganizationPublicCache::version().'.week.'.$week->id.'.programs',
            self::CACHE_TTL,
            fn () => [
                'programs' => $week->programs()
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get(['id', 'week_id', 'day_of_week', 'start_time', 'duration_minutes', 'type']),
            ],
        );

        return $this->cacheable(ApiResponse::success($data));
    }

    /**
     * Marque la réponse comme publique et mise en cache côté client
     * (A2 : revalidation serveur via expiration du cache).
     */
    private function cacheable(JsonResponse $response): JsonResponse
    {
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }
}
