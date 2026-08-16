<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Préférences de notification de l'utilisateur connecté (MOD-09-P4, US-041) :
 * consultation (activées par défaut) et remplacement complet par type.
 */
class MeNotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        return ApiResponse::success([
            'preferences' => $this->preferences->allFor($user),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $values = $request->validated('preferences');

        $this->preferences->replaceFor($user, $values);

        AuditLogger::log('notifications.preferences.update', ['types' => array_keys($values)]);

        return ApiResponse::success([
            'preferences' => $this->preferences->allFor($user),
        ]);
    }
}
