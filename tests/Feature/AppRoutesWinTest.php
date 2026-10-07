<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Models\Page;
use Atlas\Tests\TestCase;
use Illuminate\Routing\Router;

/** Atlas must never shadow routes the host application defines. */
class AppRoutesWinTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->middleware('web')->group(function (Router $router) {
            $router->get('/', fn () => 'the app homepage');
            $router->get('/about', fn () => 'the app about page');
        });
    }

    public function test_application_routes_take_precedence_over_atlas_pages(): void
    {
        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'content' => []]);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => []]);
        Page::create(['title' => 'Team', 'slug' => 'team', 'status' => 'published', 'content' => []]);

        $this->get('/')->assertOk()->assertSee('the app homepage');
        $this->get('/about')->assertOk()->assertSee('the app about page');
        $this->get('/team')->assertOk()->assertSee('Team'); // nothing else matched, Atlas answers
        $this->get('/home')->assertOk();
    }

    public function test_unknown_urls_are_still_404(): void
    {
        $this->get('/nothing-here')->assertNotFound();
    }
}
