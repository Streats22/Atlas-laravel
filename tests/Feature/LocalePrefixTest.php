<?php

namespace Atlas\Tests\Feature;

use Atlas\Facades\Atlas;
use Atlas\Models\Page;
use Atlas\Tests\TestCase;

class LocalePrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('atlas.locales', ['en' => 'English', 'nl' => 'Nederlands']);
        $app['config']->set('atlas.frontend.locale_prefix', true);
    }

    public function test_prefixed_routes_serve_translations_and_hreflang(): void
    {
        $heading = Atlas::blocks()->get('heading')->toDefinition()['template']['props'];
        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => [
            ['id' => 'h', 'type' => 'heading', 'children' => [], 'props' => array_merge((array) $heading, ['text' => 'Welcome', 'text@nl' => 'Welkom'])],
        ]]);

        $this->get('/home')->assertOk()->assertSee('Welcome')->assertSee('lang="en"', false);
        $this->get('/nl/home')->assertOk()->assertSee('Welkom')->assertSee('lang="nl"', false)
            ->assertSee('hreflang="nl" href="'.url('/nl/home').'"', false);
        $this->get('/nl')->assertOk()->assertSee('Welkom'); // home page in Dutch
        $this->get('/fr/home')->assertNotFound();
    }
}
