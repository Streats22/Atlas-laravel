<?php

declare(strict_types=1);

namespace Atlas\Blocks;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

/** Finds concrete Block subclasses inside a directory that follows PSR-4 for a namespace. */
final class BlockDiscoverer
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /** @return list<class-string<Block>> */
    public function find(string $path, string $namespace): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $found = [];
        foreach ($this->files->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = rtrim($namespace, '\\') . '\\' . $relative;

            if (class_exists($class) && is_subclass_of($class, Block::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $found[] = $class;
            }
        }

        return $found;
    }
}
