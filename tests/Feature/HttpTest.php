<?php

namespace Atlas\Tests\Feature;

use Atlas\Models\Page;
use Atlas\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HttpTest extends TestCase
{
    public function test_editor_is_forbidden_by_default_outside_local(): void
    {
        $this->get('/atlas')->assertForbidden();
        $this->post('/atlas/api/render', [])->assertForbidden();
    }

    public function test_editor_is_open_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        $this->get('/atlas')->assertOk()->assertSee('Atlas');
    }

    public function test_assets_are_public(): void
    {
        $this->get('/atlas/assets/atlas.js')->assertOk();
        $this->get('/atlas/assets/atlas.css')->assertOk();
        $this->get('/atlas/assets/secret.php')->assertNotFound();
    }

    public function test_create_edit_save_and_publish_flow(): void
    {
        $this->allowEditor();

        $this->post('/atlas/pages', ['title' => 'About Us'])->assertRedirect();
        $page = Page::firstOrFail();
        $this->assertSame('about-us', $page->slug);

        $this->get("/atlas/pages/{$page->id}/edit")->assertOk()->assertSee('atlas-config', false);

        $this->putJson("/atlas/api/pages/{$page->id}", [
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
            'content' => [['id' => 't1', 'type' => 'text', 'props' => ['text' => 'We are Atlas'], 'children' => []]],
            'css' => '.x{}', 'js' => 'window.a=1', 'head' => '',
            'meta' => ['description' => 'About page'],
        ])->assertOk()->assertJsonPath('slug', 'about');

        $page->refresh();
        $this->assertNotNull($page->published_at);
        $this->assertSame('We are Atlas', $page->content[0]['props']['text']);

        $this->get('/about')->assertOk()->assertSee('We are Atlas')->assertSee('window.a=1', false);
    }

    public function test_drafts_are_not_public_but_can_be_previewed(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'draft', 'content' => [
            ['id' => 'a', 'type' => 'text', 'props' => ['text' => 'hidden'], 'children' => []],
        ]]);

        $this->get('/secret')->assertNotFound();
        $this->get("/atlas/pages/{$page->id}/preview")->assertOk()->assertSee('hidden');
    }

    public function test_home_page_is_served_at_root(): void
    {
        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => [
            ['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Welcome'], 'children' => []],
        ]]);

        $this->get('/')->assertOk()->assertSee('Welcome');
    }

    public function test_nested_slugs_and_validation(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => []]);
        Page::create(['title' => 'Q', 'slug' => 'taken', 'content' => []]);

        $base = ['title' => 'P', 'status' => 'published', 'content' => []];

        $this->putJson("/atlas/api/pages/{$page->id}", $base + ['slug' => 'Bad Slug'])->assertJsonValidationErrors('slug');
        $this->putJson("/atlas/api/pages/{$page->id}", $base + ['slug' => 'taken'])->assertJsonValidationErrors('slug');
        $this->putJson("/atlas/api/pages/{$page->id}", $base + ['slug' => 'atlas/x'])->assertJsonValidationErrors('slug');
        $this->putJson("/atlas/api/pages/{$page->id}", $base + ['slug' => 'docs/intro'])->assertOk();

        $this->get('/docs/intro')->assertOk();
    }

    public function test_render_endpoint_renders_unsaved_state_in_edit_mode(): void
    {
        $this->allowEditor();

        $this->postJson('/atlas/api/render', [
            'title' => 'T',
            'content' => [['id' => 'z1', 'type' => 'heading', 'props' => ['text' => 'Live'], 'children' => []]],
            'css' => '.live{}',
        ])->assertOk()->assertSee('data-atlas-id="z1"', false)->assertSee('Live')->assertSee('.live{}', false);
    }

    public function test_custom_code_fields_are_ignored_when_disabled(): void
    {
        $this->allowEditor();
        config(['atlas.custom_code' => false]);
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => [], 'css' => 'keep']);

        $this->putJson("/atlas/api/pages/{$page->id}", [
            'title' => 'P', 'slug' => 'p', 'status' => 'draft', 'content' => [], 'css' => 'overwrite', 'js' => 'alert(1)',
        ])->assertOk();

        $page->refresh();
        $this->assertSame('keep', $page->css);
        $this->assertNull($page->js);
    }

    public function test_image_upload(): void
    {
        $this->allowEditor();
        Storage::fake('public');

        $this->postJson('/atlas/api/upload', ['file' => UploadedFile::fake()->image('a.png')])
            ->assertOk()->assertJsonStructure(['url']);
        $this->postJson('/atlas/api/upload', ['file' => UploadedFile::fake()->create('x.php', 1, 'text/x-php')])
            ->assertStatus(422);
    }

    public function test_delete_page(): void
    {
        $this->allowEditor();
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => []]);
        $this->delete("/atlas/pages/{$page->id}")->assertRedirect('/atlas');
        $this->assertDatabaseCount('atlas_pages', 0);
    }
}
