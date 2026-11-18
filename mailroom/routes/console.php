<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:send-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('automations:run')->everyMinute()->withoutOverlapping();
