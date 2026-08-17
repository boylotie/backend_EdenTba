<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Broadcast::routes();

        Broadcast::channel('*', function ($user, $channel) {
            \Log::info('[Reverb Backend] Channel access attempt', [
                'user_id' => $user->id,
                'channel' => $channel,
                'granted' => true,
            ]);

            return true;
        });

        require base_path('routes/channels.php');
    }
}
