<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('mobile')->plainTextToken;

        Log::info('auth.register', ['user_id' => $user->id, 'email' => $user->email]);

        return ApiResponse::success(['user' => $user, 'token' => $token], status: 201);
    }
}
