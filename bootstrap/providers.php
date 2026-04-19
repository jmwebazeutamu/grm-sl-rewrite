<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\RouteRateLimiters::class,
    App\Domain\Notification\Providers\NotificationServiceProvider::class,
];
