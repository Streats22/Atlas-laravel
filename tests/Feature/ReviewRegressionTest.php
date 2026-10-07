<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Blocks\BlockDiscoverer;
use Atlas\Facades\Atlas;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Packaging\Bundle;
use Atlas\Packaging\BundleArchive;
use Atlas\Packaging\BundleImporter;
use Atlas\Packaging\MediaUrls;
use Atlas\Support\Template;
use Atlas\Support\Theme;
use Atlas\Support\ViewHelpers;
use Atlas\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** One test per defect found in the independent code review. */
class ReviewRegressionTest extends TestCase
{
    private function render(string $type, array $props): string
    {
        return (string) Atlas::renderer()->render([['id' => 'x', 'type' => $type, 'props' => $props, 'children' => []]]);
    }

    public function test_builder_blocks_stop_rendering_and_registering_when_custom_code_is_disabled(): void
    {
        CustomBlock::create(['type' => 'danger', 'label' => 'Danger', 'fields' => [], 'html' => '<div>{{{ body }}}</div>', 'js' => 'alert(1)']);
        $node = ['id' => 'd', 'type' => 'danger', 'props' => ['body' => '<img src=x onerror=alert(1)>'], 'children' => []];

        $this->assertStringContainsString('onerror', (string) Atlas::renderer()->render([$node])); // enabled: trusted editors may

        config(['atlas.custom_code' => false]);
        $disabled = (string) Atlas::renderer()->render([$node]);
        $this->assertStringNotContainsString('onerror', $disabled);
        $this->assertStringNotContainsString('alert', $disabled);
    }

    public function test_css_url_values_cannot_break_out_of_the_declaration(): void
    {
        $payload = "a.jpg');position:fixed;inset:0;z-index:9999;background:url('//evil.test/p.png";

        foreach ([['hero', 'bg_image'], ['section', 'bg_image']] as [$type, $prop]) {
            $html = html_entity_decode($this->render($type, [$prop => $payload]));

            $this->assertStringNotContainsString("');position:fixed", $html, $type);
            $this->assertStringContainsString('%27%29%3Bposition:fixed', $html, $type); // neutralised, not removed
        }

        $carousel = html_entity_decode($this->render('carousel', ['slides' => [['image' => $payload, 'title' => 'x']]]));
        $this->assertStringNotContainsString("');position:fixed", $carousel);

        $this->assertSame('/img/a%20b.jpg', ViewHelpers::cssUrl('/img/a b.jpg'));
        $this->assertSame('#', ViewHelpers::cssUrl('javascript:alert(1)'));
    }

    public function test_style_values_are_whitelisted(): void
    {
        $evil = '100%;position:fixed;inset:0;z-index:9999';

        $this->assertStringNotContainsString('position:fixed', $this->render('image', ['src' => '/a.jpg', 'width' => $evil]));
        $this->assertStringContainsString('width:100%', $this->render('image', ['src' => '/a.jpg', 'width' => $evil])); // falls back
        $this->assertStringNotContainsString('position:fixed', $this->render('lottie', ['src' => '/a.json', 'width' => $evil]));
        $this->assertStringNotContainsString('position:fixed', $this->render('text', ['text' => 'x', 'color' => 'red;position:fixed']));
        $this->assertStringNotContainsString('position:fixed', $this->render('heading', ['text' => 'x', 'color' => 'red;position:fixed']));
        $this->assertStringNotContainsString('position:fixed', $this->render('divider', ['color' => 'red;position:fixed']));
        $this->assertStringNotContainsString('position:fixed', $this->render('section', ['background' => 'red;position:fixed']));
        $this->assertStringNotContainsString('position:fixed', $this->render('button', ['label' => 'x', 'color' => 'red;position:fixed']));

        // legitimate values still work
        $this->assertStringContainsString('color:#ff0066', $this->render('text', ['text' => 'x', 'color' => '#ff0066']));
        $this->assertStringContainsString('background:rgb(10, 20, 30)', $this->render('section', ['background' => 'rgb(10, 20, 30)']));
        $this->assertSame('var(--atlas-accent)', ViewHelpers::cssColor('var(--atlas-accent)'));
        $this->assertSame('2.5rem', ViewHelpers::cssLength('2.5rem'));
        $this->assertSame('', ViewHelpers::cssColor('url(javascript:alert(1))'));
    }

    public function test_media_references_with_traversal_are_ignored(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret.txt', 'top secret');
        Storage::disk('public')->put('atlas/ok.jpg', 'x');
        $urls = MediaUrls::fromConfig();

        $found = $urls->referenced(['a' => '{{atlas:media}}/../secret.txt', 'b' => '{{atlas:media}}/ok.jpg', 'c' => '{{atlas:media}}//etc/passwd']);

        $this->assertSame(['ok.jpg'], $found);
        $this->assertFalse(MediaUrls::isSafeName('../x'));
        $this->assertFalse(MediaUrls::isSafeName('a/../x'));
        $this->assertFalse(MediaUrls::isSafeName('/abs'));
        $this->assertTrue(MediaUrls::isSafeName('2026/05/photo.jpg'));
    }

    public function test_media_survives_a_different_uploads_directory_on_the_target_site(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('atlas/a.jpg', 'IMG');
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'status' => 'published', 'content' => [['id' => 'i', 'type' => 'image', 'props' => ['src' => MediaUrls::fromConfig()->prefix() . 'a.jpg'], 'children' => []]]]);
        $dir = sys_get_temp_dir() . '/atlas-regress-' . bin2hex(random_bytes(3));

        $bundle = app(\Atlas\Packaging\BundleExporter::class)->export(['p']);
        (new BundleArchive(MediaUrls::fromConfig()))->write($bundle, $dir . '/b.zip');
        $page->delete();

        config(['atlas.uploads.directory' => 'uploads/img']);
        $this->app->forgetInstance(MediaUrls::class);
        $archive = new BundleArchive(MediaUrls::fromConfig());
        [$read, $media] = $archive->read($dir . '/b.zip');
        $result = (new BundleImporter(MediaUrls::fromConfig(), app(\Atlas\Atlas::class), new \Atlas\Blocks\FieldNormalizer()))->import($read, $media);
        $archive->cleanup();

        $this->assertSame(1, $result->mediaCopied);
        Storage::disk('public')->assertExists('uploads/img/a.jpg');
        $this->assertStringContainsString('/uploads/img/a.jpg', Page::first()->content[0]['props']['src']);
        File::deleteDirectory($dir);
    }

    public function test_language_switcher_can_return_to_the_default_language(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => [
            ['id' => 'h', 'type' => 'heading', 'props' => ['text' => 'We are', 'text@nl' => 'Wij zijn'], 'children' => []],
            ['id' => 's', 'type' => 'language-switcher', 'props' => [], 'children' => []],
        ]]);

        $this->get('/about?lang=nl')->assertSee('Wij zijn');
        $this->get('/about')->assertSee('Wij zijn'); // remembered
        $this->get('/about?lang=en')->assertSee('We are')->assertSee('href="' . url('/about?lang=en') . '"', false);
        $this->get('/about')->assertSee('We are'); // and the session now remembers English
    }

    public function test_imports_apply_the_same_validation_as_the_editor(): void
    {
        $bundle = new Bundle(pages: [
            ['title' => 'Bad', 'slug' => 'Bad Slug/../x', 'status' => 'published', 'content' => []],
            ['title' => 'Reserved', 'slug' => 'atlas/pwned', 'status' => 'published', 'content' => []],
            ['title' => 'Atlas', 'slug' => 'atlas', 'status' => 'published', 'content' => []],
            ['title' => 'Good', 'slug' => 'b', 'status' => 'published', 'published_at' => 'not a date',
                'meta' => ['titles' => ['en' => ['x']], 'theme' => 'neon', 'evil' => 1, 'description' => 'ok'], 'content' => []],
        ]);

        $result = app(BundleImporter::class)->import($bundle);

        $this->assertSame(1, $result->pagesCreated);
        $this->assertCount(3, $result->pagesSkipped);
        $page = Page::where('slug', 'b')->firstOrFail();
        $this->assertNull($page->published_at);
        $this->assertSame(['description' => 'ok'], $page->meta); // unknown keys, bad theme and array titles dropped
        $this->get('/b')->assertOk();
    }

    public function test_the_render_endpoint_sanitises_meta(): void
    {
        $this->allowEditor();

        $this->postJson('/atlas/api/render', ['content' => [], 'meta' => ['titles' => ['en' => ['x']], 'accent' => 'red;}</style>']])->assertOk();
    }

    public function test_the_documented_theme_default_key_is_honoured(): void
    {
        config(['atlas.theme.default' => 'dark']);

        $this->assertSame('dark', Theme::default()->mode->value);
        $this->assertStringContainsString('data-atlas-theme="dark"', Page::create(['title' => 'T', 'slug' => 't', 'status' => 'published', 'content' => []])->render());
    }

    public function test_block_discovery_is_recursive_and_skips_abstract_classes(): void
    {
        $dir = sys_get_temp_dir() . '/atlas-disc-' . bin2hex(random_bytes(3));
        File::ensureDirectoryExists($dir . '/Marketing');
        $ns = 'AtlasDisc' . bin2hex(random_bytes(3));
        File::put($dir . '/BaseCard.php', "<?php\nnamespace {$ns};\nabstract class BaseCard extends \\Atlas\\Blocks\\Block {}\n");
        File::put($dir . '/Plain.php', "<?php\nnamespace {$ns};\nclass Plain extends BaseCard { public function type(): string { return 'plain-disc'; } }\n");
        File::put($dir . '/Marketing/Pricing.php', "<?php\nnamespace {$ns}\\Marketing;\nclass Pricing extends \\{$ns}\\BaseCard { public function type(): string { return 'pricing-disc'; } }\n");
        File::put($dir . '/NotABlock.php', "<?php\nnamespace {$ns};\nclass NotABlock {}\n");
        foreach (['BaseCard', 'Plain', 'Marketing/Pricing', 'NotABlock'] as $file) {
            require_once $dir . '/' . $file . '.php';
        }

        $found = app(BlockDiscoverer::class)->find($dir, $ns);
        sort($found);
        $this->assertSame(["{$ns}\\Marketing\\Pricing", "{$ns}\\Plain"], $found);

        Atlas::discover($dir, $ns);
        $this->assertTrue(Atlas::blocks()->has('pricing-disc'));
        $this->assertTrue(Atlas::blocks()->has('plain-disc'));

        $this->expectException(InvalidArgumentException::class);
        Atlas::block("{$ns}\\BaseCard");
        File::deleteDirectory($dir);
    }

    public function test_package_scaffold_rewrites_block_namespace_references_and_emits_valid_json(): void
    {
        File::ensureDirectoryExists(app_path('Atlas/Blocks/Concerns'));
        File::put(app_path('Atlas/Blocks/Concerns/HasCards.php'), "<?php\n\nnamespace App\\Atlas\\Blocks\\Concerns;\n\ntrait HasCards {}\n");
        File::put(app_path('Atlas/Blocks/Deck.php'), "<?php\n\nnamespace App\\Atlas\\Blocks;\n\nuse App\\Atlas\\Blocks\\Concerns\\HasCards;\nuse Atlas\\Blocks\\Block;\n\nclass Deck extends Block\n{\n    use HasCards;\n\n    public function type(): string\n    {\n        return 'deck';\n    }\n}\n");
        $out = sys_get_temp_dir() . '/atlas-pkg-' . bin2hex(random_bytes(3));

        $this->artisan('atlas:package', ['name' => 'acme/deck-site', '--path' => $out, '--description' => 'The "best" site \\ with quotes', '--no-pages' => true])->assertSuccessful();

        $code = file_get_contents($out . '/src/Blocks/Deck.php');
        $this->assertStringContainsString('use Acme\\DeckSite\\Blocks\\Concerns\\HasCards;', $code);
        $this->assertStringNotContainsString('App\\Atlas', $code);
        $composer = json_decode(file_get_contents($out . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('The "best" site \\ with quotes', $composer['description']);
        $this->assertStringContainsString('Atlas::discover(', file_get_contents($out . '/src/DeckSiteServiceProvider.php'));

        File::deleteDirectory($out);
        File::deleteDirectory(app_path('Atlas'));
    }

    public function test_template_engine_ignores_stray_closers_and_keeps_raw_names_root_only(): void
    {
        $this->assertSame('AB x', Template::render('A{{/if}}B {{ x }}', ['x' => 'x']));
        $this->assertSame('AB', Template::render('A{{else}}B', []));
        $this->assertSame('ROOTROOT', Template::render(
            '{{#each items}}{{ selector }}{{/each}}{{ selector }}',
            ['selector' => 'ROOT', 'items' => [['selector' => '<b>raw</b>']]],
        ), 'repeater items can no longer inject raw output through reserved names');
    }

    public function test_new_pages_never_take_a_reserved_slug(): void
    {
        $this->allowEditor();

        $this->post('/atlas/pages', ['title' => 'Atlas'])->assertRedirect();
        $this->assertTrue(Page::where('slug', 'atlas-page')->exists());
        $this->assertFalse(Page::where('slug', 'atlas')->exists());

        $copy = Page::where('slug', 'atlas-page')->first();
        $this->post("/atlas/pages/{$copy->id}/duplicate")->assertRedirect();
        $this->assertTrue(Page::where('slug', 'atlas-page-copy')->exists());
    }

    public function test_zip_imports_leave_no_temporary_directories_behind(): void
    {
        Page::create(['title' => 'P', 'slug' => 'p', 'status' => 'published', 'content' => []]);
        $zip = sys_get_temp_dir() . '/atlas-clean-' . bin2hex(random_bytes(3)) . '.zip';
        $this->artisan('atlas:export', ['file' => $zip])->assertSuccessful();
        Page::query()->delete();

        $before = glob(sys_get_temp_dir() . '/atlas-bundle-*') ?: [];
        $this->artisan('atlas:import', ['file' => $zip])->assertSuccessful();
        $after = glob(sys_get_temp_dir() . '/atlas-bundle-*') ?: [];

        $this->assertSame(count($before), count($after));
        unlink($zip);
    }
}
