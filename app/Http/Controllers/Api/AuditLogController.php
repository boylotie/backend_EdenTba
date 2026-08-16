<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('actor:id,name');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('actor_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return ApiResponse::paginate($query->latest('id')->paginate($request->integer('per_page', 15)));
    }
}
