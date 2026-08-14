<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            Log::info('auth.password.reset', ['email' => $request->validated()['email']]);

            return ApiResponse::success(['message' => 'Mot de passe réinitialisé.']);
        }

        return ApiResponse::error(
            'invalid_token',
            'Jeton de réinitialisation invalide ou expiré.',
            422,
        );
    }
}
