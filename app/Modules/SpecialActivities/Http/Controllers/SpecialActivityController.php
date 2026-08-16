<?php

namespace App\Modules\SpecialActivities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SpecialActivities\Http\Requests\StoreSessionRequest;
use App\Modules\SpecialActivities\Http\Requests\StoreSpecialActivityRequest;
use App\Modules\SpecialActivities\Http\Requests\UpdateSessionRequest;
use App\Modules\SpecialActivities\Http\Requests\UpdateSpecialActivityRequest;
use App\Modules\SpecialActivities\Models\Session;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Services\SpecialActivityService;
use App\Shared\Api\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class SpecialActivityController extends Controller
{
    public function __construct(private readonly SpecialActivityService $activities) {}

    public function store(StoreSpecialActivityRequest $request): JsonResponse
    {
        $this->authorize('create', SpecialActivity::class);

        $activity = $this->activities->create($this->activityData($request));

        return ApiResponse::success(['special_activity' => $activity], status: 201);
    }

    public function update(SpecialActivity $activity, UpdateSpecialActivityRequest $request): JsonResponse
    {
        $this->authorize('update', $activity);

        $activity = $this->activities->update($activity, $this->activityData($request));

        return ApiResponse::success(['special_activity' => $activity]);
    }

    public function destroy(SpecialActivity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        if (! $this->activities->delete($activity)) {
            return ApiResponse::error('activity_in_use', 'Cette activité est encore utilisée et ne peut pas être supprimée.', 422);
        }

        return ApiResponse::success(['message' => 'Activité supprimée.']);
    }

    public function storeSession(SpecialActivity $activity, StoreSessionRequest $request): JsonResponse
    {
        $this->authorize('update', $activity);

        try {
            $session = $this->activities->addSession($activity, $this->sessionData($request));
        } catch (DomainException) {
            return ApiResponse::error('session_overlap', 'Cette session chevauche une autre session de la même activité.', 422);
        }

        return ApiResponse::success(['session' => $session], status: 201);
    }

    public function updateSession(SpecialActivity $activity, Session $session, UpdateSessionRequest $request): JsonResponse
    {
        $this->authorize('update', $activity);

        try {
            $session = $this->activities->updateSession($session, $this->sessionData($request));
        } catch (DomainException) {
            return ApiResponse::error('session_overlap', 'Cette session chevauche une autre session de la même activité.', 422);
        }

        return ApiResponse::success(['session' => $session]);
    }

    public function destroySession(SpecialActivity $activity, Session $session): JsonResponse
    {
        $this->authorize('update', $activity);

        $this->activities->deleteSession($session);

        return ApiResponse::success(['message' => 'Session supprimée.']);
    }

    /**
     * @return array{week_id: int, activity_type_id: int, name: string, mode: string, starts_on: string|null, ends_on: string|null}
     */
    private function activityData(StoreSpecialActivityRequest|UpdateSpecialActivityRequest $request): array
    {
        return [
            'week_id' => (int) $request->integer('week_id'),
            'activity_type_id' => (int) $request->integer('activity_type_id'),
            'name' => (string) $request->string('name'),
            'mode' => (string) $request->string('mode'),
            'starts_on' => $request->filled('starts_on') ? (string) $request->string('starts_on') : null,
            'ends_on' => $request->filled('ends_on') ? (string) $request->string('ends_on') : null,
        ];
    }

    /**
     * @return array{day_of_week: int, start_time: string, duration_minutes: int, place: string|null, theme: string|null}
     */
    private function sessionData(StoreSessionRequest|UpdateSessionRequest $request): array
    {
        return [
            'day_of_week' => (int) $request->integer('day_of_week'),
            'start_time' => (string) $request->string('start_time'),
            'duration_minutes' => (int) $request->integer('duration_minutes'),
            'place' => $request->filled('place') ? (string) $request->string('place') : null,
            'theme' => $request->filled('theme') ? (string) $request->string('theme') : null,
        ];
    }
}
