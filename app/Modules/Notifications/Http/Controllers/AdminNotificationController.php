<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Http\Requests\ScheduleNotificationRequest;
use App\Modules\Notifications\Http\Requests\SendNotificationRequest;
use App\Modules\Notifications\Jobs\SendAdminNotification;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\ScheduledNotification;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Envoi manuel et programmation de notifications par l'administration
 * (US-040). L'envoi immédiat ne fait que soumettre un job ; la diffusion
 * (internes + push) est asynchrone.
 */
class AdminNotificationController extends Controller
{
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $this->authorize('send', Notification::class);

        $title = (string) $request->string('title');
        $body = $request->filled('body') ? (string) $request->string('body') : null;

        SendAdminNotification::dispatch($title, $body);

        AuditLogger::log('notifications.send', ['title' => $title]);

        return ApiResponse::success([
            'message' => 'Notification envoyée.',
            'recipients' => User::query()->where('is_active', true)->count(),
        ]);
    }

    public function schedule(ScheduleNotificationRequest $request): JsonResponse
    {
        $this->authorize('schedule', Notification::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $scheduled = ScheduledNotification::create([
            'title' => (string) $request->string('title'),
            'body' => $request->filled('body') ? (string) $request->string('body') : null,
            'scheduled_at' => $request->date('scheduled_at'),
            'created_by' => $user->id,
        ]);

        AuditLogger::log(
            'notifications.schedule',
            ['title' => $scheduled->title, 'scheduled_at' => $scheduled->scheduled_at],
            entityType: 'scheduled_notification',
            entityId: $scheduled->id,
        );

        return ApiResponse::success(['scheduled_notification' => $this->payload($scheduled)], status: 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ScheduledNotification $scheduled): array
    {
        return [
            'id' => $scheduled->id,
            'title' => $scheduled->title,
            'body' => $scheduled->body,
            'scheduled_at' => $scheduled->scheduled_at,
            'sent_at' => $scheduled->sent_at,
            'created_at' => $scheduled->created_at,
        ];
    }
}
