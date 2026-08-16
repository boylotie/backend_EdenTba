<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class PasswordUpdateController extends Controller
{
    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $user->update(['password' => $request->validated()['password']]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        AuditLogger::log('auth.password.change', actorId: $user->id, entityType: 'user', entityId: $user->id);

        return ApiResponse::success(['message' => 'Mot de passe modifié.']);
    }
}
