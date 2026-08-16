<?php

namespace App\Providers;

use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Notifications\Listeners\CreateContentPublicationNotifications;
use App\Modules\Notifications\Listeners\UpdateLastSeenAt;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Enregistrement explicite des événements métier et de leurs listeners
 * (MOD-09-P1). Les phases ultérieures ajoutent leurs mappings ici.
 */
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
    ];
}
