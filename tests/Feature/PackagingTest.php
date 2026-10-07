<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Packaging\Bundle;
use Atlas\Packaging\BundleArchive;
use Atlas\Packaging\MediaUrls;
use Atlas\Packaging\PackageSpec;
use Atlas\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use ZipArchive;

class PackagingTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/atlas-test-' . bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->tmp);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        File::deleteDirectory(app_path('Atlas'));
        File::deleteDirectory(resource_path('views/atlas'));
        parent::tearDown();
    }

    private function seedSite(): Page
    {
        Storage::disk('public')->put('atlas/hero.jpg', 'JPEGDATA');
        Storage::disk('public')->put('atlas/unused.jpg', 'NOPE');
        $url = MediaUrls::fromConfig()->prefix() . 'hero.jpg';

        CustomBlock::create(['type' => 'price-card', 'label' => 'Price Card', 'fields' => [], 'html' => '<p>{{ x }}</p>']);
        CustomBlock::create(['type' => 'unused-block', 'label' => 'Unused', 'fields' => [], 'html' => '<p>u</p>']);

        return Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'meta' => ['theme' => 'dark'], 'content' => [
            ['id' => 'a', 'type' => 'image', 'props' => ['src' => $url], 'children' => []],
            ['id' => 'b', 'type' => 'section', 'props' => [], 'children' => [['id' => 'c', 'type' => 'price-card', 'props' => [], 'children' => []]]],
        ]]);
    }

    public function test_zip_export_and_import_roundtrip_with_media_and_url_rewriting(): void
    {
        $this->seedSite();
        $zip = $this->tmp . '/site.zip';

        $this->artisan('atlas:export', ['file' => $zip])->assertSuccessful();

        $archive = new ZipArchive();
        $archive->open($zip);
        $manifest = json_decode($archive->getFromName('bundle.json'), true);
        $this->assertSame('{{atlas:media}}/hero.jpg', $manifest['pages'][0]['content'][0]['props']['src']);
        $this->assertSame(['atlas/hero.jpg'], $manifest['media']);
        $this->assertNotFalse($archive->locateName('media/hero.jpg'));
        $archive->close();

        // wipe the "site" and import into it again
        Page::query()->delete();
        CustomBlock::query()->delete();
        Storage::disk('public')->deleteDirectory('atlas');

        $this->artisan('atlas:import', ['file' => $zip])->assertSuccessful();

        $page = Page::where('slug', 'home')->firstOrFail();
        $this->assertSame(MediaUrls::fromConfig()->prefix() . 'hero.jpg', $page->content[0]['props']['src']);
        $this->assertSame('dark', $page->meta['theme']);
        Storage::disk('public')->assertExists('atlas/hero.jpg');
        $this->assertSame(2, CustomBlock::count());

        // second import skips existing items unless forced
        $this->artisan('atlas:import', ['file' => $zip])->assertSuccessful()->expectsOutputToContain('--force');
        $this->assertSame(1, Page::count());
    }

    public function test_export_can_target_pages_and_only_includes_blocks_in_use(): void
    {
        $this->seedSite();
        $bundle = app(\Atlas\Packaging\BundleExporter::class)->export(['home']);

        $this->assertSame(['price-card'], array_column($bundle->blocks, 'type'));
        $this->assertSame(['price-card', 'unused-block'], array_column(app(\Atlas\Packaging\BundleExporter::class)->export(['home'], allBlocks: true)->blocks, 'type'));
        $this->assertSame([], app(\Atlas\Packaging\BundleExporter::class)->export(['missing'])->pages);
    }

    public function test_json_export_is_content_only(): void
    {
        $this->seedSite();
        $file = $this->tmp . '/out.json';
        $this->artisan('atlas:export', ['file' => $file])->assertSuccessful();

        $this->assertJson(file_get_contents($file));
        $this->artisan('atlas:export', ['file' => $this->tmp . '/none.json', '--pages' => 'nope'])->assertFailed();
    }

    public function test_import_rejects_bad_bundles_and_zip_slip(): void
    {
        $this->artisan('atlas:import', ['file' => $this->tmp . '/missing.zip'])->assertFailed();

        $evil = $this->tmp . '/evil.zip';
        $zip = new ZipArchive();
        $zip->open($evil, ZipArchive::CREATE);
        $zip->addFromString('bundle.json', (new Bundle())->toJson());
        $zip->addFromString('../../escape.txt', 'x');
        $zip->close();

        $this->expectException(InvalidArgumentException::class);
        (new BundleArchive(MediaUrls::fromConfig()))->read($evil);
    }

    public function test_bundle_format_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Bundle::fromJson('{"format": 99}');
    }

    public function test_package_spec_validates_names(): void
    {
        $spec = PackageSpec::fromName('acme/portfolio-site');
        $this->assertSame('Acme\\PortfolioSite', $spec->namespace);
        $this->assertSame('portfolio-site', $spec->commandPrefix());

        foreach (['NoSlash', 'Acme/Bad', 'a/b/c', ''] as $bad) {
            try {
                PackageSpec::fromName($bad);
                $this->fail("{$bad} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        PackageSpec::fromName('acme/ok', 'not a namespace');
    }

    public function test_package_command_scaffolds_a_complete_package(): void
    {
        $this->seedSite();
        File::ensureDirectoryExists(app_path('Atlas/Blocks'));
        File::put(app_path('Atlas/Blocks/Banner.php'), <<<'PHP'
<?php

namespace App\Atlas\Blocks;

use App\Models\Offer;
use Atlas\Blocks\Block;

class Banner extends Block
{
    public function type(): string
    {
        return 'banner';
    }
}
PHP);
        File::ensureDirectoryExists(resource_path('views/atlas/blocks'));
        File::put(resource_path('views/atlas/blocks/banner.blade.php'), '<p>banner</p>');

        $out = $this->tmp . '/pkg';
        $this->artisan('atlas:package', ['name' => 'acme/portfolio-site', '--path' => $out, '--archive' => true, '--author' => 'Acme Inc'])
            ->expectsOutputToContain('Package created')
            ->expectsOutputToContain('App\Models\Offer')   // dependency warning
            ->assertSuccessful();

        foreach ([
            'composer.json', 'README.md', 'LICENSE', 'CHANGELOG.md', '.gitignore', '.gitattributes', 'phpunit.xml.dist',
            'src/PortfolioSiteServiceProvider.php', 'src/Console/InstallCommand.php', 'src/Blocks/Banner.php',
            'resources/views/atlas/blocks/banner.blade.php', 'resources/atlas/bundle.json', 'resources/atlas/media/hero.jpg',
            'tests/PackageTest.php', '.github/workflows/tests.yml',
        ] as $file) {
            $this->assertFileExists("{$out}/{$file}", $file);
        }
        $this->assertFileExists($out . '.zip');

        $composer = json_decode(file_get_contents("{$out}/composer.json"), true);
        $this->assertSame('acme/portfolio-site', $composer['name']);
        $this->assertSame('Acme\\PortfolioSite\\', array_key_first($composer['autoload']['psr-4']));
        $this->assertSame(['Acme\\PortfolioSite\\PortfolioSiteServiceProvider'], $composer['extra']['laravel']['providers']);
        $this->assertArrayHasKey('streats22/atlas', $composer['require']);

        $block = file_get_contents("{$out}/src/Blocks/Banner.php");
        $this->assertStringContainsString('namespace Acme\\PortfolioSite\\Blocks;', $block);
        $this->assertStringNotContainsString('namespace App\\', $block);

        $this->assertStringContainsString('Copyright (c) ' . date('Y') . ' Acme Inc', file_get_contents("{$out}/LICENSE"));
        $this->assertStringContainsString('php artisan portfolio-site:install', file_get_contents("{$out}/README.md"));
        $this->assertStringContainsString("'portfolio-site:install", file_get_contents("{$out}/src/Console/InstallCommand.php"));

        foreach (File::allFiles($out) as $file) {
            if ($file->getExtension() === 'php') {
                exec(PHP_BINARY . ' -l ' . escapeshellarg($file->getPathname()), $o, $code);
                $this->assertSame(0, $code, $file->getPathname());
            }
        }

        // refuses to overwrite without --force
        $this->artisan('atlas:package', ['name' => 'acme/portfolio-site', '--path' => $out])->assertFailed();
        $this->artisan('atlas:package', ['name' => 'acme/portfolio-site', '--path' => $out, '--force' => true])->assertSuccessful();
    }

    public function test_package_command_options_and_dry_run(): void
    {
        $this->seedSite();
        $out = $this->tmp . '/dry';

        $this->artisan('atlas:package', ['name' => 'acme/x-site', '--path' => $out, '--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($out);

        $this->artisan('atlas:package', ['name' => 'Bad Name', '--path' => $out])->assertFailed();

        $this->artisan('atlas:package', ['name' => 'acme/x-site', '--path' => $out, '--no-pages' => true, '--no-blocks' => true])->assertSuccessful();
        $this->assertFileDoesNotExist("{$out}/resources/atlas/bundle.json");
        $this->assertDirectoryDoesNotExist("{$out}/src/Blocks");
    }
}
