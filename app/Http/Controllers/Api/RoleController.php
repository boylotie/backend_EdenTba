<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\RoleRequest;
use App\Models\Role;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return ApiResponse::success(['roles' => Role::with('permissions')->orderBy('name')->get()]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'label' => $data['label'] ?? null]);
        $role->permissions()->sync($data['permissions'] ?? []);

        AuditLogger::log('roles.create', ['role_name' => $role->name, 'permissions' => $data['permissions'] ?? []], entityType: 'role', entityId: $role->id);

        return ApiResponse::success(['role' => $role->load('permissions')], status: 201);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        $role->update(['name' => $data['name'], 'label' => $data['label'] ?? null]);
        $role->permissions()->sync($data['permissions'] ?? []);

        AuditLogger::log('roles.update', ['role_name' => $role->name], entityType: 'role', entityId: $role->id);

        return ApiResponse::success(['role' => $role->load('permissions')]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->name === Role::SUPER_ADMIN) {
            return ApiResponse::error('role_locked', 'Le rôle Super Administrateur ne peut pas être supprimé.', 422);
        }

        if ($role->users()->exists()) {
            return ApiResponse::error('role_in_use', 'Ce rôle est encore attribué à des utilisateurs.', 422);
        }

        $roleId = $role->id;

        $role->delete();

        AuditLogger::log('roles.delete', entityType: 'role', entityId: $roleId);

        return ApiResponse::success(['message' => 'Role supprimé.']);
    }
}
