<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\PermissionRequest;
use App\Models\Permission;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        return ApiResponse::success(['permissions' => Permission::orderBy('name')->get()]);
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        $this->authorize('create', Permission::class);

        $data = $request->validated();

        $permission = Permission::create($data);

        AuditLogger::log('permissions.create', ['permission_name' => $permission->name], entityType: 'permission', entityId: $permission->id);

        return ApiResponse::success(['permission' => $permission], status: 201);
    }

    public function update(PermissionRequest $request, Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $permission->update($request->validated());

        AuditLogger::log('permissions.update', ['permission_name' => $permission->name], entityType: 'permission', entityId: $permission->id);

        return ApiResponse::success(['permission' => $permission]);
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);

        if ($permission->roles()->exists()) {
            return ApiResponse::error('permission_in_use', 'Cette permission est encore attribuée à un rôle.', 422);
        }

        $permissionId = $permission->id;

        $permission->delete();

        AuditLogger::log('permissions.delete', entityType: 'permission', entityId: $permissionId);

        return ApiResponse::success(['message' => 'Permission supprimée.']);
    }
}
