<?php

namespace Atlas\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'atlas:install {--no-migrate : Skip running migrations}';

    protected $description = 'Install Atlas: publish the config and run the migrations';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'atlas-config']);

        if (! $this->option('no-migrate')) {
            $this->call('migrate');
        }

        $path = config('atlas.path', 'atlas');
        $this->newLine();
        $this->info('Atlas is installed.');
        $this->line("  Editor:  /{$path}  (local environment only until you define the `useAtlas` gate)");
        $this->line('  Docs:    see the README for blocks, custom code and authorization.');

        return self::SUCCESS;
    }
}
