<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\AtlasServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AtlasServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }

    protected function allowEditor(): void
    {
        \Illuminate\Support\Facades\Gate::define('useAtlas', fn ($user = null) => true);
    }
}
