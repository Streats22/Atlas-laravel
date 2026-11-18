<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutomationEngine;
use Illuminate\Console\Command;

class RunDateTriggers extends Command
{
    protected $signature = 'automations:date-triggers';

    protected $description = 'Enrol contacts whose birthday or date field is today';

    public function handle(AutomationEngine $engine): int
    {
        $this->info('Enrolled '.$engine->runDateTriggers().' contacts.');

        return self::SUCCESS;
    }
}
