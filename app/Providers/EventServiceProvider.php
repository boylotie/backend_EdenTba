<?php

namespace App\Providers;

use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Notifications\Listeners\CreateContentPublicationNotifications;
use App\Modules\Notifications\Listeners\UpdateLastSeenAt;
use App\Modules\Streaming\Events\LiveStarted;
use App\Modules\Streaming\Listeners\SendLiveNotifications;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ContentStatusChanged::class => [
            CreateContentPublicationNotifications::class,
        ],
        Login::class => [
            UpdateLastSeenAt::class,
        ],
        LiveStarted::class => [
            SendLiveNotifications::class,
        ],
    ];
}
