<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];

        Password::sendResetLink(['email' => $email]);

        Log::info('auth.password.forgot', ['email' => $email]);

        return ApiResponse::success([
            'message' => 'Si un compte existe pour cet e-mail, un lien de réinitialisation a été envoyé.',
        ]);
    }
}
