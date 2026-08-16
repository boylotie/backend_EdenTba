<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Http\Requests\StoreDeviceRequest;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gestion des tokens d'appareil de l'utilisateur connecté (US-039) :
 * enregistrement et retrait.
 */
class DeviceController extends Controller
{
    public function __construct(private readonly DeviceTokenService $devices) {}

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $device = $this->devices->register(
            $user,
            (string) $request->string('token'),
            $request->filled('platform') ? (string) $request->string('platform') : null,
        );

        return ApiResponse::success(['device' => $this->payload($device)], status: 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        if (! $this->devices->removeForUser($user, $token)) {
            throw new NotFoundHttpException('Appareil introuvable.');
        }

        return ApiResponse::success(['message' => 'Appareil retiré.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserDevice $device): array
    {
        return [
            'id' => $device->id,
            'token' => $device->token,
            'provider' => $device->provider,
            'platform' => $device->platform,
            'last_used_at' => $device->last_used_at,
            'created_at' => $device->created_at,
        ];
    }
}
