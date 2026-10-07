<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** Describes the Composer package being generated. */
final class PackageSpec
{
    public function __construct(
        public readonly string $vendor,
        public readonly string $package,
        public readonly string $namespace,
        public readonly string $description,
        public readonly string $license = 'MIT',
        public readonly ?string $author = null,
    ) {
    }

    /** Build from "vendor/package"; the namespace defaults to Vendor\Package. */
    public static function fromName(string $name, ?string $namespace = null, ?string $description = null, string $license = 'MIT', ?string $author = null): self
    {
        if (! preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9]([_.-]?[a-z0-9]+)*$#', $name)) {
            throw new InvalidArgumentException("“{$name}” is not a valid Composer name. Use lowercase vendor/package, e.g. acme/portfolio-site.");
        }

        [$vendor, $package] = explode('/', $name);
        $namespace ??= Str::studly($vendor) . '\\' . Str::studly($package);

        if (! preg_match('/^[A-Za-z_]\w*(\\\\[A-Za-z_]\w*)*$/', $namespace)) {
            throw new InvalidArgumentException("“{$namespace}” is not a valid PHP namespace.");
        }

        return new self($vendor, $package, $namespace, $description ?: "Atlas site package {$name}", $license, $author);
    }

    public function name(): string
    {
        return $this->vendor . '/' . $this->package;
    }

    public function studly(): string
    {
        return Str::studly($this->package);
    }

    /** Console command prefix, e.g. "portfolio-site". */
    public function commandPrefix(): string
    {
        return Str::kebab($this->studly());
    }
}
