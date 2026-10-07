<?php

declare(strict_types=1);

namespace Atlas\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'atlas:install
        {--no-migrate : Skip running migrations}
        {--no-storage-link : Skip creating the public/storage symlink}';

    protected $description = 'Install Atlas: publish the config and run the migrations';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'atlas-config']);

        if (! $this->option('no-migrate')) {
            $this->call('migrate');
        }

        $this->ensureStorageLink();

        $path = config('atlas.path', 'atlas');
        $this->newLine();
        $this->info('Atlas is installed.');
        $this->line("  Editor:  /{$path}  (local environment only until you define the `useAtlas` gate)");
        $this->line('  Try it:  php artisan atlas:demo   (a complete sample page at /demo)');
        $this->line('  Docs:    see the README for blocks, custom code and authorization.');

        return self::SUCCESS;
    }

    /** Uploaded images live on the "public" disk, which needs public/storage to be served. */
    private function ensureStorageLink(): void
    {
        if ($this->option('no-storage-link') || config('atlas.uploads.disk') !== 'public' || file_exists(public_path('storage'))) {
            return;
        }

        $this->call('storage:link');
    }
}
