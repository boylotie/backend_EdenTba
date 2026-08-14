<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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

        Log::info('auth.password.change', ['user_id' => $user->id]);

        return ApiResponse::success(['message' => 'Mot de passe modifié.']);
    }
}
