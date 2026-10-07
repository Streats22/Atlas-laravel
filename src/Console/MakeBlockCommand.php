<?php

namespace Atlas\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeBlockCommand extends Command
{
    protected $signature = 'atlas:make-block {name : Block name, e.g. PricingTable} {--force : Overwrite existing files}';

    protected $description = 'Create a custom Atlas block (PHP class + Blade view)';

    public function handle(Filesystem $files): int
    {
        $class = Str::studly($this->argument('name'));
        $type = Str::kebab($class);
        $namespace = rtrim(config('atlas.discover.namespace') ?? app()->getNamespace().'Atlas\\Blocks', '\\');
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

        $files->put($classPath, strtr($files->get(__DIR__.'/../../stubs/block.stub'), [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ type }}' => $type,
            '{{ label }}' => Str::headline($class),
        ]));
        $files->put($viewPath, strtr($files->get(__DIR__.'/../../stubs/block-view.stub'), ['{{ label }}' => Str::headline($class)]));

        $this->info("Block created: {$classPath}");
        $this->info("View created:  {$viewPath}");
        $this->line('It is auto-discovered — reload the editor and find it under “Custom”.');

        return self::SUCCESS;
    }
}
