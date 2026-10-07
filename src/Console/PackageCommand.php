<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Packaging\BundleExporter;
use Atlas\Packaging\PackageScaffolder;
use Atlas\Packaging\PackageSpec;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class PackageCommand extends Command
{
    protected $signature = 'atlas:package
        {name? : Composer name, e.g. acme/portfolio-site}
        {--path= : Output directory (default: packages/<vendor>/<package>)}
        {--namespace= : PHP namespace (default: derived from the name)}
        {--description= : Package description}
        {--author= : Author name for the license}
        {--license=MIT : License identifier}
        {--pages= : Comma separated page slugs to bundle (default: all)}
        {--all-blocks : Bundle every custom block, not only those the pages use}
        {--no-pages : Do not bundle pages / custom blocks / media}
        {--no-blocks : Do not copy developer blocks (app/Atlas/Blocks) and views}
        {--no-media : Do not bundle uploaded images}
        {--archive : Also create a .zip of the package}
        {--dry-run : Show what would be packaged without writing anything}
        {--force : Overwrite the output directory if it exists}';

    protected $description = 'Package this site’s Atlas blocks, pages and media as a ready-to-publish Composer package';

    public function handle(PackageScaffolder $scaffolder, BundleExporter $exporter, Filesystem $files): int
    {
        try {
            $spec = PackageSpec::fromName(
                (string) ($this->argument('name') ?: $this->ask('Package name (vendor/package)', Str::slug((string) config('app.name', 'my-site'), '-') . '/atlas-site')),
                $this->option('namespace') ?: null,
                $this->option('description') ?: null,
                (string) $this->option('license'),
                $this->option('author') ?: null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $target = (string) ($this->option('path') ?: base_path('packages/' . $spec->vendor . '/' . $spec->package));
        $bundle = $this->option('no-pages') ? null : $exporter->export($this->slugs(), (bool) $this->option('all-blocks'), ! $this->option('no-media'));

        $this->inventory($scaffolder, $spec, $target, $bundle);

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        if ($files->exists($target) && ! $this->option('force')) {
            $this->error("{$target} already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }
        if ($files->exists($target)) {
            $files->deleteDirectory($target);
        }

        $report = $scaffolder->scaffold($spec, $target, $bundle, ! $this->option('no-blocks'), ! $this->option('no-media'));

        $this->info('Package created: ' . $target . ' (' . count($report['files']) . ' files)');
        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($this->option('archive')) {
            $this->info('Archive: ' . $this->zip($target, $files));
        }

        $this->nextSteps($spec, $target);

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function slugs(): ?array
    {
        $option = (string) $this->option('pages');

        return $option === '' ? null : array_values(array_filter(array_map('trim', explode(',', $option))));
    }

    private function inventory(PackageScaffolder $scaffolder, PackageSpec $spec, string $target, $bundle): void
    {
        $found = $scaffolder->inventory();

        $this->line("<options=bold>{$spec->name()}</>  namespace {$spec->namespace}");
        $this->table(['Included', 'Count'], [
            ['Developer blocks (app/Atlas/Blocks)', $this->option('no-blocks') ? 'skipped' : count($found['blocks'])],
            ['Block views (resources/views/atlas)', $this->option('no-blocks') ? 'skipped' : count($found['views'])],
            ['Translations (lang/vendor/atlas)', count($found['lang'])],
            ['Pages', $bundle ? count($bundle->pages) : 'skipped'],
            ['Builder blocks', $bundle ? count($bundle->blocks) : 'skipped'],
            ['Media files', $bundle ? count($bundle->media) : 'skipped'],
        ]);
        $this->line("Output: {$target}");
    }

    private function zip(string $dir, Filesystem $files): string
    {
        $archive = rtrim($dir, '/') . '.zip';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files->allFiles($dir, true) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();

        return $archive;
    }

    private function nextSteps(PackageSpec $spec, string $target): void
    {
        $relative = str_replace(base_path() . '/', '', $target);

        $this->newLine();
        $this->line('<options=bold>Next steps</>');
        $this->line("  1. cd {$relative} && composer install && vendor/bin/phpunit");
        $this->line("  2. Try it locally: composer config repositories.{$spec->package} path {$relative} && composer require {$spec->name()}");
        $this->line('  3. git init && git add . && git commit -m "Initial release" && git tag v1.0.0');
        $this->line('  4. Push to GitHub and submit the repository at https://packagist.org/packages/submit');
        $this->line("  Consumers then run: composer require {$spec->name()} && php artisan {$spec->commandPrefix()}:install");
    }
}
