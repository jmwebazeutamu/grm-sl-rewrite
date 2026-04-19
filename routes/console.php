<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('queue:prune-failed')->daily();
