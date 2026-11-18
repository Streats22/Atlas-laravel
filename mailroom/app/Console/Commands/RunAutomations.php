<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutomationEngine;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Advance automation runs whose wait step has finished';

    public function handle(AutomationEngine $engine): int
    {
        $this->info('Processed '.$engine->processDue().' automation runs.');

        return self::SUCCESS;
    }
}
