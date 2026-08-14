<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            Log::info('auth.login.failed', ['email' => $credentials['email']]);

            return ApiResponse::error('invalid_credentials', 'Identifiants incorrects.', 401);
        }

        if (! $user->is_active) {
            Log::info('auth.login.disabled', ['user_id' => $user->id]);

            return ApiResponse::error('account_disabled', 'Compte désactivé.', 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        Log::info('auth.login', ['user_id' => $user->id]);

        return ApiResponse::success(['user' => $user, 'token' => $token]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => $request->user()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $user->currentAccessToken()->delete();

        Log::info('auth.logout', ['user_id' => $user->id]);

        return ApiResponse::success();
    }
}
