<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Illuminate\Support\Facades\Storage;

/**
 * Makes uploaded-image URLs portable: the site's storage URL becomes a placeholder
 * on export and is swapped for the target site's storage URL on import.
 */
final class MediaUrls
{
    public const PLACEHOLDER = '{{atlas:media}}/';

    public function __construct(private readonly string $disk, private readonly string $directory)
    {
    }

    public static function fromConfig(): self
    {
        return new self((string) config('atlas.uploads.disk', 'public'), trim((string) config('atlas.uploads.directory', 'atlas'), '/'));
    }

    public function disk(): string
    {
        return $this->disk;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** The URL prefix under which uploads are served, e.g. https://site.test/storage/atlas/ */
    public function prefix(): string
    {
        return rtrim(Storage::disk($this->disk)->url($this->directory . '/x'), 'x');
    }

    /** Replace this site's upload URLs with the placeholder, recursively. */
    public function export(array $data): array
    {
        return $this->swap($data, $this->prefix(), self::PLACEHOLDER);
    }

    /** Replace the placeholder with this site's upload URL prefix, recursively. */
    public function import(array $data): array
    {
        return $this->swap($data, self::PLACEHOLDER, $this->prefix());
    }

    /** @return list<string> Upload file names relative to the uploads directory (e.g. "a.jpg", "2026/b.png"). */
    public function referenced(array $data): array
    {
        $found = [];
        $needle = [$this->prefix(), self::PLACEHOLDER];

        array_walk_recursive($data, function ($value) use (&$found, $needle): void {
            if (! is_string($value)) {
                return;
            }
            foreach ($needle as $prefix) {
                $offset = 0;
                while (($pos = strpos($value, $prefix, $offset)) !== false) {
                    $start = $pos + strlen($prefix);
                    if (preg_match('/^[\w.\-\/]+/', substr($value, $start), $m) && self::isSafeName($m[0])) {
                        $found[$m[0]] = true;
                    }
                    $offset = $start;
                }
            }
        });

        return array_keys($found);
    }

    /** A relative upload name: no traversal, no absolute path, no hidden segments. */
    public static function isSafeName(string $name): bool
    {
        return $name !== ''
            && ! str_starts_with($name, '/')
            && ! str_contains($name, '//')
            && ! preg_match('#(^|/)\.{1,2}(/|$)#', $name);
    }

    /** Path of an upload name on the configured disk. */
    public function path(string $name): string
    {
        return $this->directory . '/' . $name;
    }

    private function swap(array $data, string $from, string $to): array
    {
        array_walk_recursive($data, static function (&$value) use ($from, $to): void {
            if (is_string($value) && str_contains($value, $from)) {
                $value = str_replace($from, $to, $value);
            }
        });

        return $data;
    }
}
