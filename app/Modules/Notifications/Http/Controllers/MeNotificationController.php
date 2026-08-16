<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Services\NotificationService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications internes de l'utilisateur connecté (US-038) : liste paginée
 * (lu/non lu) et marquage lu. Un utilisateur ne voit que ses notifications.
 */
class MeNotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $paginator = $this->notifications->paginatedFor(
            $user,
            $request->boolean('unread'),
            (int) $request->integer('per_page', 10),
            (int) $request->integer('page', 1),
        );

        $paginator->through(fn (Notification $notification): array => $this->payload($notification));

        return ApiResponse::paginate($paginator, ['unread_count' => $this->notifications->unreadCount($user)]);
    }

    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $notification = $user->userNotifications()->findOrFail($notification);

        $notification = $this->notifications->markAsRead($notification);

        return ApiResponse::success(['notification' => $this->payload($notification)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'entity' => $notification->entity_type !== null
                ? ['type' => $notification->entity_type, 'id' => $notification->entity_id]
                : null,
            'read' => $notification->isRead(),
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
