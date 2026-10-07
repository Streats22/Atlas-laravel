<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Packaging\BundleArchive;
use Atlas\Packaging\BundleImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportCommand extends Command
{
    protected $signature = 'atlas:import
        {file : Bundle to import (.zip, .json or a directory)}
        {--force : Overwrite pages and blocks that already exist}';

    protected $description = 'Import pages, custom blocks and media from an Atlas bundle';

    public function handle(BundleArchive $archive, BundleImporter $importer): int
    {
        try {
            [$bundle, $media] = $archive->read((string) $this->argument('file'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $result = $importer->import($bundle, $media, (bool) $this->option('force'));

        $this->table(['', 'Created', 'Updated', 'Skipped'], [
            ['Pages', $result->pagesCreated, $result->pagesUpdated, count($result->pagesSkipped)],
            ['Custom blocks', $result->blocksCreated, $result->blocksUpdated, count($result->blocksSkipped)],
        ]);
        $this->info("Media files copied: {$result->mediaCopied}");

        foreach (array_merge($result->pagesSkipped, $result->blocksSkipped) as $skipped) {
            $this->line(" skipped: {$skipped}");
        }
        if ($result->pagesSkipped || $result->blocksSkipped) {
            $this->comment('Use --force to overwrite existing items.');
        }

        return self::SUCCESS;
    }
}
