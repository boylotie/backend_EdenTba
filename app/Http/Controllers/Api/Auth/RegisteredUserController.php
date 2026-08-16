<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('mobile')->plainTextToken;

        AuditLogger::log('auth.register', ['email' => $user->email], actorId: $user->id, entityType: 'user', entityId: $user->id);

        return ApiResponse::success(['user' => $user, 'token' => $token], status: 201);
    }
}
