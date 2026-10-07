<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Facades\Atlas;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecurityTest extends TestCase
{
    private const PAYLOADS = [
        "' OR '1'='1",
        "'; DROP TABLE atlas_pages; --",
        '" OR ""="',
        "x' UNION SELECT * FROM users --",
        '1; DELETE FROM atlas_blocks',
        "\\' OR 1=1 --",
    ];

    public function test_source_never_builds_raw_sql(): void
    {
        $banned = '/(DB::(raw|select|statement|unprepared|insert|update|delete)|whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw|joinRaw|->raw\()/';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src'));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->assertDoesNotMatchRegularExpression($banned, (string) file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    public function test_hostile_slugs_never_reach_the_database_as_sql(): void
    {
        Page::create(['title' => 'Real', 'slug' => 'real', 'status' => 'published', 'content' => []]);

        foreach (self::PAYLOADS as $payload) {
            $this->get('/' . rawurlencode($payload))->assertNotFound();
            $this->get('/real?lang=' . rawurlencode($payload))->assertOk();
            $this->assertSame('', (string) Atlas::page($payload));
            $this->assertSame('', trim(Blade::render('<x-atlas::page :slug="$s" />', ['s' => $payload])));
        }

        $this->assertDatabaseCount('atlas_pages', 1);
        $this->assertTrue(\Schema::hasTable('atlas_pages') && \Schema::hasTable('atlas_blocks'));
    }

    public function test_editor_inputs_are_bound_not_interpolated(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => []]);

        foreach (self::PAYLOADS as $payload) {
            // slug / block type are validated by strict patterns
            $this->putJson("/atlas/api/pages/{$page->id}", ['title' => 'P', 'slug' => $payload, 'status' => 'draft', 'content' => []])
                ->assertJsonValidationErrors('slug');
            $this->postJson('/atlas/api/blocks', ['label' => 'X', 'type' => $payload, 'fields' => []])
                ->assertJsonValidationErrors('type');

            // free-text is stored verbatim and never executed
            $this->post('/atlas/pages', ['title' => $payload])->assertRedirect();
        }

        $this->assertSame(1 + count(self::PAYLOADS), Page::count());
        $this->assertTrue(Page::where('title', "'; DROP TABLE atlas_pages; --")->exists());
        $this->assertSame(0, CustomBlock::count());
    }

    public function test_sql_in_content_is_stored_literally_and_rendered_escaped(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => []]);
        $evil = "'); DELETE FROM atlas_pages; --";

        $this->putJson("/atlas/api/pages/{$page->id}", [
            'title' => $evil, 'slug' => 'p', 'status' => 'published',
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => $evil], 'children' => []]],
            'meta' => ['description' => $evil],
        ])->assertOk();

        $this->assertSame($evil, $page->refresh()->content[0]['props']['text']);
        $this->get('/p')->assertOk()->assertSee(e($evil), false);
        $this->assertDatabaseCount('atlas_pages', 1);
    }

    public function test_queries_use_bindings(): void
    {
        $log = [];
        DB::listen(function ($query) use (&$log) {
            $log[] = $query;
        });

        Page::create(['title' => 'Real', 'slug' => 'real', 'status' => 'published', 'content' => []]);
        $this->get('/real')->assertOk();
        $this->get('/' . rawurlencode("x' OR 1=1"))->assertNotFound();

        $selects = array_filter($log, fn ($q) => str_contains($q->sql, 'atlas_pages') && str_starts_with($q->sql, 'select'));
        $this->assertNotEmpty($selects);
        foreach ($selects as $query) {
            $this->assertStringContainsString('?', $query->sql);
            $this->assertStringNotContainsString('OR 1=1', $query->sql);
        }
    }

    public function test_models_are_not_mass_assignable_beyond_their_fillable_list(): void
    {
        $page = new Page();
        $page->fill(['title' => 'x', 'id' => 99, 'created_at' => '2000-01-01']);

        $this->assertNull($page->id);
        $this->assertSame('x', $page->title);
    }
}
