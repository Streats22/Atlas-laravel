<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Packaging\BundleArchive;
use Atlas\Packaging\BundleExporter;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'atlas:export
        {file=atlas-export.zip : Output file (.zip with media, .json content-only, or a directory)}
        {--pages= : Comma separated page slugs (default: all pages)}
        {--all-blocks : Include every custom block, not only the ones the exported pages use}
        {--no-media : Do not include uploaded images}';

    protected $description = 'Export pages, custom blocks and media into a portable bundle';

    public function handle(BundleExporter $exporter, BundleArchive $archive): int
    {
        $slugs = $this->option('pages') ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('pages'))))) : null;
        $bundle = $exporter->export($slugs, (bool) $this->option('all-blocks'), ! $this->option('no-media'));

        if ($bundle->isEmpty()) {
            $this->warn('Nothing to export — no matching pages or custom blocks.');

            return self::FAILURE;
        }

        $path = $archive->write($bundle, (string) $this->argument('file'), ! $this->option('no-media'));

        $this->info("Exported {$this->count($bundle->pages)} page(s), {$this->count($bundle->blocks)} custom block(s) and {$this->count($bundle->media)} media file(s) to {$path}");

        return self::SUCCESS;
    }

    private function count(array $items): int
    {
        return count($items);
    }
}
