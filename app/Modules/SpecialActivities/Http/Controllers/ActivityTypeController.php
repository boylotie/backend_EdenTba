<?php

namespace App\Modules\SpecialActivities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SpecialActivities\Http\Requests\StoreActivityTypeRequest;
use App\Modules\SpecialActivities\Http\Requests\UpdateActivityTypeRequest;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Services\ActivityTypeService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActivityTypeController extends Controller
{
    public function __construct(private readonly ActivityTypeService $types) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ActivityType::class);

        return ApiResponse::success([
            'activity_types' => ActivityType::query()->orderBy('label')->get(),
        ]);
    }

    public function store(StoreActivityTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ActivityType::class);

        $data = [
            'code' => (string) $request->string('code'),
            'label' => (string) $request->string('label'),
            'is_active' => $request->boolean('is_active', true),
        ];

        $type = $this->types->create($data);

        return ApiResponse::success(['activity_type' => $type], status: 201);
    }

    public function update(ActivityType $activityType, UpdateActivityTypeRequest $request): JsonResponse
    {
        $this->authorize('update', $activityType);

        $data = [
            'code' => (string) $request->string('code'),
            'label' => (string) $request->string('label'),
            'is_active' => $request->boolean('is_active', $activityType->is_active),
        ];

        $type = $this->types->update($activityType, $data);

        return ApiResponse::success(['activity_type' => $type]);
    }

    public function destroy(ActivityType $activityType): JsonResponse
    {
        $this->authorize('delete', $activityType);

        if (! $this->types->delete($activityType)) {
            return ApiResponse::error('type_in_use', 'Ce type est encore utilisé et ne peut pas être supprimé.', 422);
        }

        return ApiResponse::success(['message' => 'Type supprimé.']);
    }
}
