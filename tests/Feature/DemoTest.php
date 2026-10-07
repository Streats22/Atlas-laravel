<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Models\Page;
use Atlas\Tests\TestCase;

class DemoTest extends TestCase
{
    public function test_demo_command_builds_a_page_that_renders_without_errors_in_both_modes(): void
    {
        $this->artisan('atlas:demo')->assertSuccessful();

        $page = Page::where('slug', 'demo')->firstOrFail();
        $this->assertGreaterThan(10, count($page->content));

        $this->assertStringNotContainsString('class="atlas-error"', $page->render());
        $this->assertStringNotContainsString('class="atlas-error"', $page->render(editing: true));

        $this->get('/demo')->assertOk()->assertSee('Designer &amp; developer who ships', false)->assertSee('/atlas/assets/demo/1.jpg', false);

        // refuses to clobber unless forced
        $this->artisan('atlas:demo')->assertFailed();
        $this->artisan('atlas:demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Page::where('slug', 'demo')->count());
    }

    public function test_demo_images_are_served_and_whitelisted(): void
    {
        foreach (range(1, 8) as $n) {
            $this->get("/atlas/assets/demo/{$n}.jpg")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        }

        $this->get('/atlas/assets/demo/9.jpg')->assertNotFound();
        $this->get('/atlas/assets/demo/..%2F..%2Fcomposer.json')->assertNotFound();
        $this->get('/atlas/assets/demo/1.php')->assertNotFound();
    }

    public function test_page_spacing_setting_changes_the_css_variables(): void
    {
        $page = Page::create(['title' => 'S', 'slug' => 's', 'status' => 'published', 'content' => [], 'meta' => ['spacing' => 'spacious']]);

        $html = $page->render();
        $this->assertStringContainsString('--atlas-space:2.5rem', $html);
        $this->assertStringContainsString('--atlas-section-y:112px', $html);
        $this->assertStringContainsString('<main class="atlas-main">', $html);

        $default = Page::create(['title' => 'D', 'slug' => 'd', 'status' => 'published', 'content' => []])->render();
        $this->assertStringContainsString('--atlas-space:1.5rem', $default);
    }

    public function test_full_bleed_blocks_are_flagged_for_the_page_gutters(): void
    {
        $html = (string) \Atlas\Facades\Atlas::renderer()->render([
            ['id' => 'a', 'type' => 'section', 'props' => [], 'children' => []],
            ['id' => 'b', 'type' => 'text', 'props' => ['text' => 'x'], 'children' => []],
        ]);

        $this->assertMatchesRegularExpression('/atlas-b-section[^"]*atlas-fullbleed|atlas-fullbleed[^"]*atlas-b-section/', $html);
        $this->assertDoesNotMatchRegularExpression('/atlas-b-text[^"]*atlas-fullbleed/', $html);
    }

    public function test_runtime_labels_and_aria_labels_follow_the_locale(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        $this->artisan('atlas:demo')->assertSuccessful();

        $this->get('/demo')->assertSee('"close":"Close"', false)->assertSee('aria-label="Filter projects"', false);
        $this->get('/demo?lang=nl')->assertSee('"close":"Sluiten"', false)->assertSee('"slide":"Dia :n"', false)->assertSee('aria-label="Projecten filteren"', false)->assertSee('aria-label="Sociale media"', false);
    }
}
