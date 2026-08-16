<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePermission;
use App\Modules\Content\Console\PublishScheduledContents;
use App\Modules\Notifications\Console\SendDueScheduledNotifications;
use App\Modules\Notifications\Console\SendInactivityReminders;
use App\Modules\Organization\Console\SendProgramReminders;
use App\Modules\Streaming\Console\LiveRelayCommand;
use App\Providers\EventServiceProvider;
use App\Shared\Api\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withProviders([
        EventServiceProvider::class,
    ])
    ->withCommands([
        PublishScheduledContents::class,
        SendDueScheduledNotifications::class,
        SendProgramReminders::class,
        SendInactivityReminders::class,
        LiveRelayCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            return ApiExceptionRenderer::render($exception, $request);
        });
    })->create();
