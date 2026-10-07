<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Generates a ready-to-publish Composer package from a site's Atlas assets:
 * developer blocks (+ views + translations) and an exported content bundle.
 */
final class PackageScaffolder
{
    /** @var list<string> Warnings collected for the report (e.g. blocks that reference App\ classes). */
    private array $warnings = [];

    /** @var list<string> */
    private array $written = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly BundleArchive $archive,
    ) {
    }

    /** Where the app keeps the things we can package. */
    public function inventory(): array
    {
        $blocksPath = $this->blocksPath();

        return [
            'blocks' => is_dir($blocksPath) ? collect($this->files->allFiles($blocksPath))->filter(fn (SplFileInfo $f) => $f->getExtension() === 'php')->map(fn (SplFileInfo $f) => $f->getRelativePathname())->values()->all() : [],
            'views' => is_dir(resource_path('views/atlas')) ? collect($this->files->allFiles(resource_path('views/atlas')))->map(fn (SplFileInfo $f) => $f->getRelativePathname())->values()->all() : [],
            'lang' => is_dir(lang_path('vendor/atlas')) ? collect($this->files->allFiles(lang_path('vendor/atlas')))->map(fn (SplFileInfo $f) => $f->getRelativePathname())->values()->all() : [],
        ];
    }

    /**
     * @param  array{blocks?: bool}  $options
     * @return array{files: list<string>, warnings: list<string>}
     */
    public function scaffold(PackageSpec $spec, string $target, ?Bundle $bundle, bool $withBlocks = true, bool $withMedia = true): array
    {
        $this->warnings = [];
        $this->written = [];

        $copiedBlocks = $withBlocks ? $this->copyBlocks($spec, $target) : [];
        $hasViews = $withBlocks && $this->copyDirectory(resource_path('views/atlas'), $target . '/resources/views/atlas');
        $hasLang = $this->copyDirectory(lang_path('vendor/atlas'), $target . '/lang');
        $hasBundle = $bundle !== null && ! $bundle->isEmpty();

        if ($hasBundle) {
            $this->archive->write($bundle, $target . '/resources/atlas', $withMedia);
            $this->written[] = 'resources/atlas/bundle.json';
        }

        $vars = $this->variables($spec, $hasBundle, $hasViews, $hasLang);
        $this->render('composer.json.stub', $target . '/composer.json', $vars);
        $this->render('provider.stub', $target . '/src/' . $vars['{{ class }}'] . 'ServiceProvider.php', $vars);
        $this->render('install-command.stub', $target . '/src/Console/InstallCommand.php', $vars);
        $this->render('README.md.stub', $target . '/README.md', $vars);
        $this->render('LICENSE.stub', $target . '/LICENSE', $vars);
        $this->render('CHANGELOG.md.stub', $target . '/CHANGELOG.md', $vars);
        $this->render('gitignore.stub', $target . '/.gitignore', $vars);
        $this->render('gitattributes.stub', $target . '/.gitattributes', $vars);
        $this->render('phpunit.xml.stub', $target . '/phpunit.xml.dist', $vars);
        $this->render('test.stub', $target . '/tests/PackageTest.php', $vars);
        $this->render('workflow.stub', $target . '/.github/workflows/tests.yml', $vars);

        return ['files' => $this->written, 'warnings' => array_merge($this->warnings, $this->lint($target)), 'blocks' => $copiedBlocks];
    }

    private function blocksPath(): string
    {
        return config('atlas.discover.path') ?? app_path('Atlas/Blocks');
    }

    private function blocksNamespace(): string
    {
        return rtrim(config('atlas.discover.namespace') ?? app()->getNamespace() . 'Atlas\\Blocks', '\\');
    }

    /** @return list<string> Copied block class names */
    private function copyBlocks(PackageSpec $spec, string $target): array
    {
        $source = $this->blocksPath();
        if (! is_dir($source)) {
            return [];
        }

        $copied = [];
        foreach ($this->files->allFiles($source) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativeDir = trim(str_replace('/', '\\', $file->getRelativePath()), '\\');
            $namespace = $spec->namespace . '\\Blocks' . ($relativeDir !== '' ? '\\' . $relativeDir : '');
            $code = (string) preg_replace('/^namespace\s+[^;]+;/m', "namespace {$namespace};", $file->getContents(), 1);

            foreach ($this->appReferences($code) as $reference) {
                $this->warnings[] = "{$file->getRelativePathname()} references {$reference} — bundle that class too, or the package will fail outside this app.";
            }

            $this->put($target . '/src/Blocks/' . $file->getRelativePathname(), $code);
            $copied[] = $namespace . '\\' . $file->getFilenameWithoutExtension();
        }

        return $copied;
    }

    /** @return list<string> App\… classes a block depends on. */
    private function appReferences(string $code): array
    {
        $root = rtrim(app()->getNamespace(), '\\');
        $own = $this->blocksNamespace();
        preg_match_all('/\b' . preg_quote($root, '/') . '\\\\[\w\\\\]+/', $code, $m);

        return array_values(array_unique(array_filter($m[0], fn (string $ref) => ! str_starts_with($ref, $own))));
    }

    private function copyDirectory(string $from, string $to): bool
    {
        if (! is_dir($from)) {
            return false;
        }

        foreach ($this->files->allFiles($from) as $file) {
            $this->put($to . '/' . $file->getRelativePathname(), $file->getContents());
        }

        return true;
    }

    private function variables(PackageSpec $spec, bool $hasBundle, bool $hasViews, bool $hasLang): array
    {
        $atlasConstraint = $this->atlasConstraint();

        return [
            '{{ name }}' => $spec->name(),
            '{{ vendor }}' => $spec->vendor,
            '{{ package }}' => $spec->package,
            '{{ description }}' => $spec->description,
            '{{ license }}' => $spec->license,
            '{{ author }}' => $spec->author ?? $spec->vendor,
            '{{ namespace }}' => $spec->namespace,
            '{{ namespace_json }}' => str_replace('\\', '\\\\', $spec->namespace),
            '{{ class }}' => $spec->studly(),
            '{{ command }}' => $spec->commandPrefix(),
            '{{ atlas_constraint }}' => $atlasConstraint,
            '{{ year }}' => date('Y'),
            '{{ has_bundle }}' => $hasBundle ? 'true' : 'false',
            '{{ has_views }}' => $hasViews ? 'true' : 'false',
            '{{ has_lang }}' => $hasLang ? 'true' : 'false',
        ];
    }

    /** The Atlas version constraint for the generated package ("^1.2" when tagged, otherwise "*"). */
    private function atlasConstraint(): string
    {
        $version = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('streats22/atlas')
            ? (string) InstalledVersions::getPrettyVersion('streats22/atlas')
            : '';

        return preg_match('/^v?(\d+)\.(\d+)/', $version, $m) ? "^{$m[1]}.{$m[2]}" : '*';
    }

    private function render(string $stub, string $target, array $vars): void
    {
        $this->put($target, strtr($this->files->get(__DIR__ . '/../../stubs/package/' . $stub), $vars));
    }

    private function put(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->written[] = $path;
    }

    /** php -l every generated PHP file. @return list<string> problems */
    private function lint(string $target): array
    {
        $problems = [];
        foreach (File::allFiles($target) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
            if ($code !== 0) {
                $problems[] = 'Syntax problem in ' . $file->getRelativePathname() . ': ' . implode(' ', $output);
            }
        }

        return $problems;
    }
}
