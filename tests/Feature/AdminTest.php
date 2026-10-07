<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Enums\PageStatus;
use Atlas\Models\Page;
use Atlas\Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_duplicating_a_page_creates_an_unpublished_copy_with_a_unique_slug(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => [['id' => 'a', 'type' => 'text', 'props' => ['text' => 'hi'], 'children' => []]]]);

        $this->post("/atlas/pages/{$page->id}/duplicate")->assertRedirect();
        $this->post("/atlas/pages/{$page->id}/duplicate")->assertRedirect();

        $copies = Page::where('id', '!=', $page->id)->orderBy('id')->get();
        $this->assertSame(['about-copy', 'about-copy-2'], $copies->pluck('slug')->all());
        $this->assertSame('About (copy)', $copies[0]->title);
        $this->assertSame(PageStatus::Draft, $copies[0]->status);
        $this->assertNull($copies[0]->published_at);
        $this->assertSame($page->content, $copies[0]->content);
    }

    public function test_search_filters_pages_safely(): void
    {
        $this->allowEditor();
        Page::create(['title' => 'Contact us', 'slug' => 'contact', 'content' => []]);
        Page::create(['title' => '100% wool', 'slug' => 'wool', 'content' => []]);
        Page::create(['title' => 'Other', 'slug' => 'other', 'content' => []]);

        $this->get('/atlas?q=contact')->assertOk()->assertSee('Contact us')->assertDontSee('Other');
        $this->get('/atlas?q=' . rawurlencode('100%'))->assertOk()->assertSee('100% wool')->assertDontSee('Contact us');
        $this->get('/atlas?q=' . rawurlencode("' OR 1=1 --"))->assertOk()->assertDontSee('Contact us');
        $this->assertDatabaseCount('atlas_pages', 3);
    }

    public function test_the_page_list_is_paginated(): void
    {
        $this->allowEditor();
        foreach (range(1, 25) as $i) {
            Page::create(['title' => "Page {$i}", 'slug' => "page-{$i}", 'content' => []]);
        }

        $this->get('/atlas')->assertOk()->assertSee('1 / 2')->assertSee('page=2', false);
        $this->get('/atlas?page=2')->assertOk()->assertSee('2 / 2');
    }

    public function test_sitemap_lists_published_pages_with_alternates(): void
    {
        $this->app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => []]);
        Page::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'draft', 'content' => []]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $xml = $response->getContent();

        $this->assertStringContainsString('<loc>' . url('/home') . '</loc>', $xml);
        $this->assertStringContainsString('hreflang="nl" href="' . url('/home?lang=nl') . '"', htmlspecialchars_decode($xml));
        $this->assertStringNotContainsString('secret', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap must be well-formed XML');
    }

    public function test_public_pages_have_a_canonical_link(): void
    {
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => []]);

        $this->get('/about')->assertSee('<link rel="canonical" href="' . url('/about') . '">', false);
    }
}
