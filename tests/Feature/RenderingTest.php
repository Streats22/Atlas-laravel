<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;
use Atlas\Facades\Atlas;
use Atlas\Models\Page;
use Atlas\Tests\TestCase;
use Illuminate\Support\Facades\View;

class RenderingTest extends TestCase
{
    public function test_builtin_blocks_are_registered_and_blade_block_is_off_by_default(): void
    {
        $types = array_keys(Atlas::blocks()->all());

        foreach (['section', 'columns', 'heading', 'text', 'image', 'button', 'spacer', 'divider', 'custom-code'] as $type) {
            $this->assertContains($type, $types);
        }
        $this->assertNotContains('blade', $types);
    }

    public function test_props_are_escaped_and_blocks_are_wrapped(): void
    {
        $html = (string) Atlas::renderer()->render([
            ['id' => 'h1', 'type' => 'heading', 'props' => ['text' => '<script>alert(1)</script>', 'level' => 'h1; evil'], 'children' => []],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('id="atlas-h1"', $html);
        $this->assertStringContainsString('<h2', $html); // invalid level falls back
        $this->assertStringNotContainsString('data-atlas-id', $html); // only in edit mode
    }

    public function test_edit_mode_adds_markers_and_placeholders(): void
    {
        $html = (string) Atlas::renderer()->render([['id' => 's1', 'type' => 'section', 'props' => [], 'children' => []]], editing: true);

        $this->assertStringContainsString('data-atlas-id="s1"', $html);
        $this->assertStringContainsString('data-atlas-container="1"', $html);
        $this->assertStringContainsString('Drop blocks here', $html);
    }

    public function test_containers_render_children(): void
    {
        $html = (string) Atlas::renderer()->render([
            ['id' => 'c', 'type' => 'columns', 'props' => [], 'children' => [
                ['id' => 't1', 'type' => 'text', 'props' => ['text' => '**bold** and [safe](https://example.com) [bad](javascript:alert(1))'], 'children' => []],
            ]],
        ]);

        $this->assertStringContainsString('grid-template-columns:1fr 1fr', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_custom_code_block_outputs_raw_html_scoped_css_and_isolated_js(): void
    {
        $html = (string) Atlas::renderer()->render([[
            'id' => 'cc', 'type' => 'custom-code', 'children' => [],
            'props' => [
                'html' => '<div class="x"><b>raw</b></div>',
                'css' => '{{selector}} .x { color: red }',
                'js' => 'el.dataset.ran = "1";',
            ],
        ]]);

        $this->assertStringContainsString('<div class="x"><b>raw</b></div>', $html);
        $this->assertStringContainsString('#atlas-cc .x { color: red }', $html);
        $this->assertStringContainsString('document.getElementById("atlas-cc")', $html);
        $this->assertStringContainsString('el.dataset.ran = "1";', $html);
    }

    public function test_custom_js_does_not_run_inside_editor_canvas(): void
    {
        $html = (string) Atlas::renderer()->render([[
            'id' => 'cc', 'type' => 'custom-code', 'children' => [], 'props' => ['js' => 'alert(1)'],
        ]], editing: true);

        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_per_block_css_class_id_and_custom_css(): void
    {
        $html = (string) Atlas::renderer()->render([[
            'id' => 'a1', 'type' => 'spacer', 'children' => [],
            'props' => ['css_class' => 'my-class', 'html_id' => 'hero', 'custom_css' => '{{selector}}{outline:1px solid}'],
        ]]);

        $this->assertStringContainsString('id="hero"', $html);
        $this->assertStringContainsString('my-class', $html);
        $this->assertStringContainsString('#hero{outline:1px solid}', $html);
    }

    public function test_custom_code_can_be_disabled(): void
    {
        config(['atlas.custom_code' => false]);
        $html = (string) Atlas::renderer()->render([[
            'id' => 'a1', 'type' => 'spacer', 'children' => [], 'props' => ['custom_css' => '.evil{}'],
        ]]);
        $this->assertStringNotContainsString('.evil', $html);
    }

    public function test_developer_blocks_run_custom_php_and_views(): void
    {
        View::addNamespace('t', __DIR__ . '/../views');
        Atlas::block(new class () extends Block {
            public function type(): string
            {
                return 'greeter';
            }
            public function view(): string
            {
                return 't::greeter';
            }
            public function fields(): array
            {
                return [Field::text('who', 'Who', 'world')];
            }
            public function data(array $props): array
            {
                return ['shout' => strtoupper($props['who'])];
            }
        });

        $html = (string) Atlas::renderer()->render([['id' => 'g', 'type' => 'greeter', 'props' => ['who' => 'atlas'], 'children' => []]]);

        $this->assertStringContainsString('Hello ATLAS', $html);
        $this->assertContains('greeter', array_column(Atlas::blocks()->definitions(), 'type'));
    }

    public function test_view_block_helper(): void
    {
        View::addNamespace('t', __DIR__ . '/../views');
        Atlas::viewBlock('hero', 't::hero', 'Hero', [Field::text('title', 'Title', 'Hi')]);

        $html = (string) Atlas::renderer()->render([['id' => 'h', 'type' => 'hero', 'props' => [], 'children' => []]]);
        $this->assertStringContainsString('<h1>Hi</h1>', $html);
    }

    public function test_a_broken_block_does_not_break_the_page(): void
    {
        Atlas::block(new class () extends Block {
            public function type(): string
            {
                return 'boom';
            }
            public function render(array $props, \Illuminate\Support\HtmlString $children, array $node, bool $editing): string
            {
                throw new \RuntimeException('kaboom');
            }
        });
        $nodes = [
            ['id' => 'b', 'type' => 'boom', 'props' => [], 'children' => []],
            ['id' => 't', 'type' => 'text', 'props' => ['text' => 'still here'], 'children' => []],
        ];

        $this->assertStringContainsString('still here', (string) Atlas::renderer()->render($nodes));
        $this->assertStringContainsString('kaboom', (string) Atlas::renderer()->render($nodes, editing: true));
    }

    public function test_unknown_blocks_are_skipped_publicly(): void
    {
        $nodes = [['id' => 'x', 'type' => 'removed-block', 'props' => [], 'children' => []]];
        $this->assertSame('', (string) Atlas::renderer()->render($nodes));
        $this->assertStringContainsString('Unknown block', (string) Atlas::renderer()->render($nodes, editing: true));
    }

    public function test_page_document_includes_page_css_js_head_and_global_assets(): void
    {
        Atlas::style('https://cdn.example/lib.css')->script('https://cdn.example/lib.js', defer: true);
        $page = Page::create([
            'title' => 'Doc', 'slug' => 'doc', 'status' => 'published', 'content' => [],
            'css' => 'body{background:#123}', 'js' => 'console.log("page js")', 'head' => '<meta name="x-test" content="1">',
            'meta' => ['description' => 'Desc'],
        ]);

        $html = $page->render();

        $this->assertStringContainsString('<title>Doc</title>', $html);
        $this->assertStringContainsString('body{background:#123}', $html);
        $this->assertStringContainsString('console.log("page js")', $html);
        $this->assertStringContainsString('<meta name="x-test" content="1">', $html);
        $this->assertStringContainsString('https://cdn.example/lib.css', $html);
        $this->assertStringContainsString('<script src="https://cdn.example/lib.js" defer>', $html);
        $this->assertStringContainsString('content="Desc"', $html);
        $this->assertStringNotContainsString('console.log("page js")', $page->render(editing: true));
    }

    public function test_blade_component_embeds_a_published_page(): void
    {
        Page::create(['title' => 'Footer', 'slug' => 'footer', 'status' => 'published', 'css' => '.f{}', 'content' => [
            ['id' => 'a', 'type' => 'text', 'props' => ['text' => 'embedded!'], 'children' => []],
        ]]);

        $out = \Illuminate\Support\Facades\Blade::render('<x-atlas::page slug="footer" />');
        $this->assertStringContainsString('embedded!', $out);
        $this->assertStringContainsString('.f{}', $out);
        $this->assertStringContainsString('embedded!', (string) Atlas::page('footer'));
    }

    public function test_blade_block_only_when_enabled(): void
    {
        $this->app['config']->set('atlas.allow_blade_code', true);
        (new \Atlas\AtlasServiceProvider($this->app))->boot();

        $html = (string) Atlas::renderer()->render([[
            'id' => 'bb', 'type' => 'blade', 'children' => [], 'props' => ['code' => '<p>{{ 1 + 2 }}</p>'],
        ]]);

        $this->assertStringContainsString('<p>3</p>', $html);
    }
}
