<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SettingsUpdateRequest;
use App\Models\Setting;
use App\Settings\SettingsService;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::success(['settings' => app(SettingsService::class)->all()]);
    }

    public function update(SettingsUpdateRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $values = $request->validated('settings');

        app(SettingsService::class)->replace($values);

        AuditLogger::log('settings.update', ['keys' => array_keys($values)]);

        return ApiResponse::success(['settings' => app(SettingsService::class)->all()]);
    }
}
