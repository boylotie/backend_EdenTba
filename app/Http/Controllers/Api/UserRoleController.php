<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\UserRoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function update(UserRoleRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', $user);

        $roles = $request->validated()['roles'];

        if ($user->hasRole(Role::SUPER_ADMIN) && ! in_array(Role::SUPER_ADMIN, $roles, true)) {
            $superAdminCount = Role::where('name', Role::SUPER_ADMIN)->firstOrFail()->users()->count();

            if ($superAdminCount <= 1) {
                return ApiResponse::error('last_super_admin', 'Impossible de retirer le dernier Super Administrateur.', 422);
            }
        }

        $user->syncRoles($roles);

        AuditLogger::log('users.roles.update', ['roles' => $roles], entityType: 'user', entityId: $user->id);

        return ApiResponse::success(['user' => $user->load('roles')]);
    }
}
