<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Reads and writes bundles in three layouts:
 *   *.json  – content only
 *   *.zip   – bundle.json + media/…
 *   a directory – bundle.json + media/…  (used inside generated packages)
 */
final class BundleArchive
{
    public const MANIFEST = 'bundle.json';

    /** @var list<string> */
    private array $temp = [];

    public function __construct(private readonly MediaUrls $urls)
    {
    }

    /** Write the bundle; the layout follows the path ("x.json", "x.zip" or a directory). */
    public function write(Bundle $bundle, string $path, bool $withMedia = true): string
    {
        return match (true) {
            str_ends_with($path, '.json') => $this->writeJson($bundle, $path),
            str_ends_with($path, '.zip') => $this->writeZip($bundle, $path, $withMedia),
            default => $this->writeDirectory($bundle, $path, $withMedia),
        };
    }

    /**
     * @return array{0: Bundle, 1: ?string} The bundle and the directory holding its media (if any)
     */
    public function read(string $path): array
    {
        if (is_dir($path)) {
            return [$this->readManifest($path . '/' . self::MANIFEST), is_dir($path . '/media') ? $path . '/media' : null];
        }
        if (! is_file($path)) {
            throw new InvalidArgumentException("Bundle not found: {$path}");
        }
        if (str_ends_with($path, '.zip')) {
            return $this->readZip($path);
        }

        return [$this->readManifest($path), null];
    }

    private function writeJson(Bundle $bundle, string $path): string
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $bundle->toJson());

        return $path;
    }

    private function writeDirectory(Bundle $bundle, string $dir, bool $withMedia): string
    {
        File::ensureDirectoryExists($dir);
        File::put($dir . '/' . self::MANIFEST, $bundle->toJson());

        if ($withMedia) {
            foreach ($this->safeMedia($bundle) as $name) {
                $target = $dir . '/media/' . $name;
                File::ensureDirectoryExists(dirname($target));
                File::put($target, Storage::disk($this->urls->disk())->get($this->urls->path($name)));
            }
        }

        return $dir;
    }

    private function writeZip(Bundle $bundle, string $path, bool $withMedia): string
    {
        File::ensureDirectoryExists(dirname($path));
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$path}");
        }

        $zip->addFromString(self::MANIFEST, $bundle->toJson());
        if ($withMedia) {
            foreach ($this->safeMedia($bundle) as $name) {
                $zip->addFromString('media/' . $name, Storage::disk($this->urls->disk())->get($this->urls->path($name)));
            }
        }
        $zip->close();

        return $path;
    }

    /** @return array{0: Bundle, 1: ?string} */
    private function readZip(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException("Could not open {$path}");
        }

        $tmp = sys_get_temp_dir() . '/atlas-bundle-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($tmp);
        $this->temp[] = $tmp;

        // Refuse path traversal ("zip slip") before extracting anything.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();

                throw new InvalidArgumentException("Unsafe path in bundle: {$name}");
            }
        }
        $zip->extractTo($tmp);
        $zip->close();

        return [$this->readManifest($tmp . '/' . self::MANIFEST), is_dir($tmp . '/media') ? $tmp . '/media' : null];
    }

    private function readManifest(string $file): Bundle
    {
        if (! is_file($file)) {
            throw new InvalidArgumentException('Missing ' . self::MANIFEST . ' in the bundle.');
        }

        return Bundle::fromJson((string) file_get_contents($file));
    }

    /** @return list<string> */
    private function safeMedia(Bundle $bundle): array
    {
        return array_values(array_filter($bundle->media, MediaUrls::isSafeName(...)));
    }

    /** Remove temporary directories created while reading zip bundles. */
    public function cleanup(): void
    {
        foreach ($this->temp as $dir) {
            File::deleteDirectory($dir);
        }
        $this->temp = [];
    }
}
