<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Models\Page;
use Atlas\Tests\TestCase;

class AdminBarTest extends TestCase
{
    public function test_visitors_never_see_the_toolbar_or_drafts(): void
    {
        Page::create(['title' => 'Live', 'slug' => 'live', 'status' => 'published', 'content' => []]);
        Page::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'draft', 'content' => []]);

        $this->get('/live')->assertOk()->assertDontSee('atlas-adminbar', false);
        $this->get('/secret')->assertNotFound();
    }

    public function test_editors_get_a_toolbar_linking_to_the_editor(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'Live', 'slug' => 'live', 'status' => 'published', 'content' => []]);

        $response = $this->get('/live')->assertOk()->assertSee('id="atlas-adminbar"', false)
            ->assertSee(route('atlas.pages.edit', $page), false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('X-Robots-Tag'));
    }

    public function test_draft_previews_carry_the_toolbar_so_editors_can_get_back(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'draft', 'content' => []]);

        $this->get('/secret')->assertNotFound();
        $this->get("/atlas/pages/{$page->id}/preview")->assertOk()->assertSee('data-status="draft"', false)
            ->assertSee(route('atlas.pages.edit', $page), false);
    }
}
