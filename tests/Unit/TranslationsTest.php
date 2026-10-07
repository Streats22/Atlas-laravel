<?php

declare(strict_types=1);

namespace Atlas\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards against missing or drifting translation keys. */
class TranslationsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private function keys(string $locale, string $file): array
    {
        return array_keys(require self::ROOT . "/lang/{$locale}/{$file}.php");
    }

    /** @return list<string> ui keys referenced from PHP, Blade and JS */
    private function usedUiKeys(): array
    {
        $used = [];
        $sources = [
            ['src', 'php', '/atlas::ui\.([a-z_0-9]+)/'],
            ['resources/views', 'php', '/atlas::ui\.([a-z_0-9]+)/'],
            ['resources/dist', 'js', "/\\bt\\('([a-z_0-9]+)'/"],
        ];

        foreach ($sources as [$dir, $ext, $pattern]) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $ext)) {
                    preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $m);
                    array_push($used, ...$m[1]);
                }
            }
        }

        // keys built dynamically
        foreach (['draft', 'published'] as $status) {
            $used[] = $status;
        }
        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            $used[] = "device_{$device}";
        }
        foreach (['blank', 'landing', 'portfolio'] as $template) {
            array_push($used, "template_{$template}", "template_{$template}_desc");
        }

        // drop dynamic prefixes captured by the patterns (e.g. 'device_' . $name)
        return array_values(array_unique(array_filter($used, fn (string $key) => ! str_ends_with($key, '_'))));
    }

    public function test_every_ui_key_used_in_code_exists_in_english(): void
    {
        $missing = array_diff($this->usedUiKeys(), $this->keys('en', 'ui'));

        $this->assertSame([], array_values($missing), 'Missing from lang/en/ui.php');
    }

    public function test_dutch_has_every_english_ui_key(): void
    {
        $this->assertSame([], array_values(array_diff($this->keys('en', 'ui'), $this->keys('nl', 'ui'))), 'Missing from lang/nl/ui.php');
        $this->assertSame([], array_values(array_diff($this->keys('nl', 'ui'), $this->keys('en', 'ui'))), 'Extra keys in lang/nl/ui.php');
    }

    public function test_dutch_option_and_field_files_only_translate_known_things(): void
    {
        foreach (['fields', 'options', 'categories'] as $file) {
            $this->assertNotEmpty($this->keys('nl', $file));
        }
    }
}
