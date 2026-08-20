<?php

namespace App\Modules\Streaming\Listeners;

use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Streaming\Events\LiveStarted;
use App\Modules\Streaming\Events\LiveStopped;

class SendLiveNotifications
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly DeviceTokenService $devices,
    ) {}

    public function handle(LiveStarted|LiveStopped $event): void
    {
        if ($event instanceof LiveStarted) {
            $this->handleStarted($event);
        }
    }

    private function handleStarted(LiveStarted $event): void
    {
        $this->notifications->createForLiveStarted(
            $event->session->id,
            $event->session->title,
        );

        $tokens = $this->devices->tokensOfActiveUsers(NotificationService::TYPE_LIVE_STARTED);

        if ($tokens === []) {
            return;
        }

        $title = __('Le direct a commencé');
        $body = $event->session->title ?? null;

        SendPushNotifications::dispatch(
            $tokens,
            $title,
            $body,
            NotificationService::ENTITY_LIVE_SESSION,
            $event->session->id,
            NotificationService::TYPE_LIVE_STARTED,
        );
    }
}
