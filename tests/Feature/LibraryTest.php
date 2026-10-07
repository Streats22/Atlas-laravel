<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Facades\Atlas;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Support\PageMeta;
use Atlas\Support\Template;
use Atlas\Support\Theme;
use Atlas\Tests\TestCase;

class LibraryTest extends TestCase
{
    /** Build a node (with default children) from a block's editor template. */
    private function node(string $type, array $props = []): array
    {
        $tpl = Atlas::blocks()->get($type)->toDefinition()['template'];

        return [
            'id' => 'n' . substr(md5($type), 0, 6),
            'type' => $type,
            'props' => array_merge((array) $tpl['props'], $props),
            'children' => array_map(fn ($c) => ['id' => 'c' . substr(md5(json_encode($c)), 0, 6), 'type' => $c['type'], 'props' => (array) ($c['props'] ?? []), 'children' => []], $tpl['children']),
        ];
    }

    public function test_every_builtin_block_renders_with_its_defaults_in_both_modes(): void
    {
        $this->app['config']->set('atlas.allow_blade_code', true);
        (new \Atlas\AtlasServiceProvider($this->app))->boot();
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);

        $types = array_keys(Atlas::blocks()->all());
        $this->assertGreaterThanOrEqual(28, count($types));

        foreach ($types as $type) {
            foreach ([false, true] as $editing) {
                $html = (string) Atlas::renderer()->render([$this->node($type)], $editing);
                $this->assertStringNotContainsString('atlas-error', $html, "{$type} failed (editing=" . json_encode($editing) . ')');
                $this->assertStringContainsString('atlas-b-' . $type, $html, "{$type} missing wrapper");
            }
        }
    }

    public function test_portfolio_grid_has_filters_lightbox_and_escapes_content(): void
    {
        $html = (string) Atlas::renderer()->render([$this->node('portfolio-grid', ['items' => [
            ['image' => 'https://x.test/a.jpg', 'title' => '<b>One</b>', 'category' => 'Web', 'url' => '', 'description' => '', 'tags' => 'a, b'],
            ['image' => 'https://x.test/b.jpg', 'title' => 'Two', 'category' => 'App', 'url' => 'javascript:alert(1)', 'description' => '', 'tags' => ''],
        ]])]);

        $this->assertStringContainsString('data-filter="Web"', $html);
        $this->assertStringContainsString('data-filter="App"', $html);
        $this->assertStringContainsString('data-atlas-lightbox', $html);
        $this->assertStringNotContainsString('<b>One</b>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_translations_pick_the_current_locale_with_fallback(): void
    {
        $node = ['id' => 'h', 'type' => 'heading', 'children' => [], 'props' => [
            'text' => 'Hello', 'text@nl' => 'Hallo', 'eyebrow' => 'Welcome',
        ]];
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);

        $this->assertStringContainsString('Hello', (string) Atlas::renderer()->render([$node]));

        app()->setLocale('nl');
        $html = (string) Atlas::renderer()->render([$node]);
        $this->assertStringContainsString('Hallo', $html);
        $this->assertStringContainsString('Welcome', $html); // no nl value → falls back

        // repeater items are localised too
        $acc = ['id' => 'a', 'type' => 'accordion', 'children' => [], 'props' => ['items' => [['title' => 'Q', 'title@nl' => 'Vraag', 'text' => 'A']]]];
        $this->assertStringContainsString('Vraag', (string) Atlas::renderer()->render([$acc]));
    }

    public function test_ui_translations_exist_for_dutch(): void
    {
        app()->setLocale('nl');
        $this->assertSame('Opslaan', __('atlas::ui.save'));
        $this->assertSame('Sectie', Atlas::blocks()->get('section')->label());
        $this->assertSame('Inhoud', Atlas::blocks()->get('heading')->toDefinition()['category']);
        $this->assertSame('Kleur', collect(Atlas::blocks()->get('heading')->toDefinition()['fields'])->firstWhere('name', 'color')['label']);
    }

    public function test_theme_css_and_modes(): void
    {
        $auto = Theme::default()->css();
        $this->assertStringContainsString('prefers-color-scheme: dark', $auto);
        $this->assertStringContainsString('--atlas-accent:#4f46e5', $auto);

        $dark = Theme::fromMeta(PageMeta::from(['theme' => 'dark', 'accent_dark' => '#00ff88']))->css();
        $this->assertStringContainsString('--atlas-accent:#00ff88', $dark);
        $this->assertStringNotContainsString('prefers-color-scheme', $dark);

        $light = Theme::fromMeta(PageMeta::from(['theme' => 'light', 'accent' => 'not-a-colour']))->css();
        $this->assertStringContainsString('--atlas-accent:#4f46e5', $light); // invalid colour ignored
        $this->assertSame('#ffffff', Theme::contrast('#4f46e5'));
        $this->assertSame('#0f172a', Theme::contrast('#ffeeaa'));
    }

    public function test_page_document_has_theme_runtime_and_toggle(): void
    {
        $page = Page::create(['title' => 'T', 'slug' => 't', 'status' => 'published', 'meta' => ['theme' => 'dark', 'theme_toggle' => true, 'accent' => '#ff0066'], 'content' => [
            ['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Hi'], 'children' => [], ] + [],
        ]]);
        $page->content = [array_merge($this->node('heading'), ['props' => ['text' => 'Hi', 'anim' => 'fade-up', 'anim_hover' => 'lift', 'visibility' => 'hide-mobile']])];
        $page->save();

        $html = $page->render();

        $this->assertStringContainsString('data-atlas-theme="dark"', $html);
        $this->assertStringContainsString('--atlas-accent:#ff0066', $html);
        $this->assertStringContainsString('data-atlas-theme-toggle', $html);
        $this->assertStringContainsString('id="atlas-runtime"', $html);
        $this->assertStringContainsString('data-atlas-anim="fade-up"', $html);
        $this->assertStringContainsString('atlas-hover-lift', $html);
        $this->assertStringContainsString('atlas-hide-mobile', $html);
        $this->assertStringContainsString("localStorage.getItem('atlas-theme')", $html);

        // editor canvas: no runtime, no early script, no toggle
        $edit = $page->render(editing: true);
        $this->assertStringNotContainsString('id="atlas-runtime"', $edit);
        $this->assertStringNotContainsString('data-atlas-theme-toggle', $edit);
    }

    public function test_runtime_is_omitted_when_nothing_needs_it(): void
    {
        $page = Page::create(['title' => 'Plain', 'slug' => 'plain', 'status' => 'published', 'content' => [$this->node('text')]]);
        $this->assertStringNotContainsString('id="atlas-runtime"', $page->render());
    }

    public function test_lottie_block_loads_its_library_only_when_used(): void
    {
        $with = Page::create(['title' => 'L', 'slug' => 'l', 'content' => [$this->node('lottie', ['src' => 'https://x.test/a.json'])]]);
        $without = Page::create(['title' => 'N', 'slug' => 'n', 'content' => [$this->node('text')]]);

        $this->assertStringContainsString('lottie-web', $with->render());
        $this->assertStringNotContainsString('lottie-web', $without->render());
    }

    public function test_template_engine(): void
    {
        $out = Template::render(
            '<h1>{{ title }}</h1>{{{ raw }}}{{#each items}}<li>{{ @number }}.{{ name }}</li>{{/each}}{{#if show}}Y{{else}}N{{/if}}<a href="{{ url:link }}">',
            ['title' => '<x>', 'raw' => '<i>r</i>', 'items' => [['name' => 'A&B'], ['name' => 'C']], 'show' => '', 'link' => 'javascript:alert(1)'],
        );

        $this->assertSame('<h1>&lt;x&gt;</h1><i>r</i><li>1.A&amp;B</li><li>2.C</li>N<a href="#">', $out);
        $this->assertSame('Y', Template::render('{{#if a}}Y{{else}}N{{/if}}', ['a' => [1]]));
        $this->assertSame('N', Template::render('{{#if a}}Y{{else}}N{{/if}}', ['a' => []]));
        $this->assertSame('ab', Template::render('{{#each x}}{{ value }}{{/each}}', ['x' => ['a', 'b']]));
    }

    public function test_block_builder_api_creates_renders_updates_and_deletes_blocks(): void
    {
        $this->allowEditor();

        $res = $this->postJson('/atlas/api/blocks', [
            'label' => 'Price Card', 'type' => 'price-card', 'category' => 'Shop', 'icon' => '💰', 'container' => false,
            'html' => '<div class="p">{{ plan }} — {{#each perks}}<i>{{ perk }}</i>{{/each}}</div>',
            'css' => '{{selector}} .p{color:red}', 'js' => 'el.dataset.x=1',
            'fields' => [
                ['name' => 'plan', 'label' => 'Plan', 'type' => 'text', 'default' => 'Pro', 'translatable' => true],
                ['name' => 'perks', 'type' => 'repeater', 'default' => [['perk' => 'Fast']], 'fields' => [['name' => 'perk', 'type' => 'text']]],
            ],
        ])->assertOk()->assertJsonPath('definition.type', 'price-card')->assertJsonPath('definition.custom', true);

        $id = $res->json('block.id');
        $this->assertSame('Pro', $res->json('definition.template.props.plan'));

        $html = (string) Atlas::renderer()->render([[
            'id' => 'pc', 'type' => 'price-card', 'children' => [],
            'props' => ['plan' => 'Business', 'plan@nl' => 'Zakelijk', 'perks' => [['perk' => 'Fast'], ['perk' => '<b>x</b>']]],
        ]]);
        $this->assertStringContainsString('Business — <i>Fast</i><i>&lt;b&gt;x&lt;/b&gt;</i>', $html);
        $this->assertStringContainsString('#atlas-pc .p{color:red}', $html);
        $this->assertStringContainsString('getElementById("atlas-pc")', $html);

        // duplicates and clashes with code blocks are rejected
        $this->postJson('/atlas/api/blocks', ['label' => 'X', 'type' => 'price-card', 'fields' => []])->assertJsonValidationErrors('type');
        $this->postJson('/atlas/api/blocks', ['label' => 'X', 'type' => 'heading', 'fields' => []])->assertJsonValidationErrors('type');
        $this->postJson('/atlas/api/blocks', ['label' => 'X', 'type' => 'ok-block', 'fields' => [['name' => 'Bad Name', 'type' => 'text']]])->assertJsonValidationErrors('fields.0.name');

        $this->putJson("/atlas/api/blocks/{$id}", ['label' => 'Price Card 2', 'type' => 'ignored', 'html' => '<p>v2</p>', 'fields' => []])->assertOk();
        $this->assertSame('price-card', CustomBlock::find($id)->type);
        $this->assertStringContainsString('<p>v2</p>', (string) Atlas::renderer()->render([['id' => 'pc', 'type' => 'price-card', 'props' => [], 'children' => []]]));

        $this->deleteJson("/atlas/api/blocks/{$id}")->assertOk();
        $this->assertDatabaseCount('atlas_blocks', 0);
        $this->assertNull(Atlas::blocks()->get('price-card'));
    }

    public function test_block_builder_is_disabled_without_custom_code(): void
    {
        $this->allowEditor();
        config(['atlas.custom_code' => false]);
        $this->postJson('/atlas/api/blocks', ['label' => 'X', 'type' => 'xx', 'fields' => []])->assertForbidden();
    }

    public function test_editor_config_contains_locales_theme_i18n_and_custom_blocks(): void
    {
        $this->allowEditor();
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        CustomBlock::create(['type' => 'mine', 'label' => 'Mine', 'fields' => [], 'html' => '<p>x</p>']);
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => []]);

        $res = $this->get("/atlas/pages/{$page->id}/edit")->assertOk();
        $json = json_decode(html_entity_decode(preg_replace('/^.*?<script id="atlas-config"[^>]*>(.*?)<\/script>.*$/s', '$1', $res->getContent())), true);

        $this->assertSame(['en' => 'English', 'nl' => 'Nederlands'], $json['locales']['available']);
        $this->assertSame('Save', $json['i18n']['save']);
        $this->assertSame('mine', $json['customBlocks'][0]['type']);
        $this->assertContains('mine', array_column($json['blocks'], 'type'));
        $this->assertSame('auto', $json['theme']['mode']);
        $this->assertContains('anim', array_column($json['common'], 'name'));
    }

    public function test_page_meta_is_whitelisted_and_translated_titles_are_saved(): void
    {
        $this->allowEditor();
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'content' => []]);

        $this->putJson("/atlas/api/pages/{$page->id}", [
            'title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => [],
            'meta' => ['description' => 'D', 'theme' => 'dark', 'theme_toggle' => true, 'accent' => '#123456', 'font' => 'serif', 'evil' => 'x',
                'titles' => ['nl' => 'Thuis', 'xx' => 'nope', 'en' => ''], 'descriptions' => ['nl' => 'Beschrijving']],
        ])->assertOk();

        $meta = $page->refresh()->meta;
        $this->assertSame('dark', $meta['theme']);
        $this->assertTrue($meta['theme_toggle']);
        $this->assertSame(['nl' => 'Thuis'], $meta['titles']);
        $this->assertArrayNotHasKey('evil', $meta);

        $this->putJson("/atlas/api/pages/{$page->id}", ['title' => 'H', 'slug' => 'home', 'status' => 'draft', 'content' => [], 'meta' => ['theme' => 'neon']])
            ->assertJsonValidationErrors('meta.theme');
    }

    public function test_translated_titles_and_lang_attributes_on_public_pages(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'meta' => ['titles' => ['nl' => 'Over ons']], 'content' => [
            array_merge($this->node('heading'), ['props' => ['text' => 'We are', 'text@nl' => 'Wij zijn']]),
        ]]);

        $this->get('/about')->assertOk()->assertSee('<title>About</title>', false)->assertSee('lang="en"', false)->assertSee('We are');
        $this->get('/about?lang=nl')->assertOk()->assertSee('<title>Over ons</title>', false)->assertSee('lang="nl"', false)->assertSee('Wij zijn')
            ->assertSee('hreflang="nl"', false);
        // remembered for the session
        $this->get('/about')->assertSee('Wij zijn');
    }

    public function test_locale_urls(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => []]);

        $this->assertSame(url('/home?lang=nl'), \Atlas\Support\Locales::url($page, 'nl'));
        $this->assertSame(url('/home'), \Atlas\Support\Locales::url($page, 'en'));

        $this->app['config']->set('atlas.frontend.locale_prefix', true);
        $this->assertSame(url('/nl/home'), \Atlas\Support\Locales::url($page, 'nl'));
        $this->assertSame(url('/home'), \Atlas\Support\Locales::url($page, 'en'));
    }

    public function test_language_switcher_links(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        $html = (string) Atlas::renderer()->render([$this->node('language-switcher')]);
        $this->assertStringContainsString('hreflang="nl"', $html);
        $this->assertStringContainsString('lang=nl', $html);
    }

    public function test_make_block_options_and_list_command(): void
    {
        $class = app_path('Atlas/Blocks/FancyBox.php');
        $view = resource_path('views/atlas/blocks/fancy-box.blade.php');
        \Illuminate\Support\Facades\File::delete([$class, $view]);

        $this->artisan('atlas:make-block', ['name' => 'FancyBox', '--container' => true, '--group' => 'Marketing', '--icon' => '✨'])->assertSuccessful();

        $src = file_get_contents($class);
        $this->assertStringContainsString("return 'Marketing';", $src);
        $this->assertStringContainsString('return true;', $src);
        $this->assertStringContainsString('$children', file_get_contents($view));
        exec('php -l ' . escapeshellarg($class), $out, $code);
        $this->assertSame(0, $code);

        \Illuminate\Support\Facades\File::delete([$class, $view]);
        $this->artisan('atlas:blocks')->assertSuccessful()->expectsOutputToContain('portfolio-grid');
    }
}
