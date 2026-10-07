<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Facades\Atlas;
use Atlas\Models\Page;
use Atlas\Templates\NodeFactory;
use Atlas\Templates\PageTemplate;
use Atlas\Tests\TestCase;

class TemplatesTest extends TestCase
{
    public function test_builtin_templates_are_registered_and_render_cleanly(): void
    {
        $this->assertSame(['blank', 'landing', 'portfolio'], Atlas::templates()->keys());

        foreach (Atlas::templates()->all() as $key => $template) {
            $page = Page::create(['title' => $key, 'slug' => $key, 'status' => 'published', 'content' => $template->tree(new NodeFactory(Atlas::getFacadeRoot())), 'meta' => $template->meta()]);

            foreach ([false, true] as $editing) {
                $this->assertStringNotContainsString('class="atlas-error"', $page->render($editing), "{$key} editing=" . json_encode($editing));
            }
        }
    }

    public function test_creating_a_page_from_a_template(): void
    {
        $this->allowEditor();

        $this->post('/atlas/pages', ['title' => 'Launch', 'template' => 'landing'])->assertRedirect();
        $page = Page::where('slug', 'launch')->firstOrFail();

        $this->assertSame('hero', $page->content[0]['type']);
        $this->assertSame('draft', $page->status->value);

        $this->post('/atlas/pages', ['title' => 'Empty'])->assertRedirect();
        $this->assertSame([], Page::where('slug', 'empty')->firstOrFail()->content);

        $this->post('/atlas/pages', ['title' => 'Bad', 'template' => 'nope'])->assertSessionHasErrors('template');
        $this->post('/atlas/pages', ['title' => 'Bad', 'template' => "x' OR 1=1"])->assertSessionHasErrors('template');
    }

    public function test_templates_are_extensible(): void
    {
        Atlas::template(new class () extends PageTemplate {
            public function key(): string
            {
                return 'pricing';
            }

            public function tree(NodeFactory $nodes): array
            {
                return [$nodes->make('heading', ['text' => 'Pricing'])];
            }

            public function meta(): array
            {
                return ['theme' => 'dark'];
            }
        });
        $this->allowEditor();

        $this->assertSame('Pricing', Atlas::templates()->get('pricing')->label());
        $this->get('/atlas')->assertSee('value="pricing"', false);

        $this->post('/atlas/pages', ['title' => 'Plans', 'template' => 'pricing'])->assertRedirect();
        $page = Page::where('slug', 'plans')->firstOrFail();
        $this->assertSame('Pricing', $page->content[0]['props']['text']);
        $this->assertSame('dark', $page->meta['theme']);
    }

    public function test_template_labels_are_translated(): void
    {
        app()->setLocale('nl');

        $this->assertSame('Landingspagina', Atlas::templates()->get('landing')->label());
        $this->assertSame('Een leeg canvas', Atlas::templates()->get('blank')->description());
    }
}
