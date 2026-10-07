<?php

declare(strict_types=1);

namespace Atlas\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeBlockCommand extends Command
{
    protected $signature = 'atlas:make-block {name : Block name, e.g. PricingTable}
        {--container : Let other blocks be dropped inside it}
        {--group=Custom : Palette group}
        {--icon=▢ : Palette icon}
        {--force : Overwrite existing files}';

    protected $description = 'Create a custom Atlas block (PHP class + Blade view)';

    public function handle(Filesystem $files): int
    {
        $class = Str::studly($this->argument('name'));
        $type = Str::kebab($class);
        $namespace = rtrim(config('atlas.discover.namespace') ?? app()->getNamespace() . 'Atlas\\Blocks', '\\');
        $dir = config('atlas.discover.path') ?? app_path('Atlas/Blocks');

        $classPath = "{$dir}/{$class}.php";
        $viewPath = resource_path("views/atlas/blocks/{$type}.blade.php");

        foreach ([$classPath, $viewPath] as $path) {
            if ($files->exists($path) && ! $this->option('force')) {
                $this->error("{$path} already exists. Use --force to overwrite.");

                return self::FAILURE;
            }
        }

        $files->ensureDirectoryExists($dir);
        $files->ensureDirectoryExists(dirname($viewPath));

        $files->put($classPath, strtr($files->get(__DIR__ . '/../../stubs/block.stub'), [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ type }}' => $type,
            '{{ label }}' => Str::headline($class),
            '{{ group }}' => $this->option('group'),
            '{{ icon }}' => $this->option('icon'),
            '{{ container }}' => $this->option('container') ? 'true' : 'false',
        ]));
        $files->put($viewPath, strtr($files->get(__DIR__ . '/../../stubs/block-view.stub'), ['{{ label }}' => Str::headline($class), '{{ type }}' => $type, '{{ children }}' => $this->option('container') ? "\n    <div class=\"{{ \$domId }}-children\">{!! \$children !!}</div>" : '']));

        $this->info("Block created: {$classPath}");
        $this->info("View created:  {$viewPath}");
        $this->line('It is auto-discovered — reload the editor and find it under “Custom”.');

        return self::SUCCESS;
    }
}
