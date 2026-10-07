<?php

namespace Atlas;

use Atlas\Blocks\Block;
use Atlas\Blocks\BlockRegistry;
use Atlas\Blocks\Builtin;
use Atlas\Console\InstallCommand;
use Atlas\Console\MakeBlockCommand;
use Atlas\Http\Controllers\FrontendController;
use Atlas\Http\Middleware\Authorize;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atlas.php', 'atlas');

        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(Atlas::class, fn ($app) => new Atlas($app->make(BlockRegistry::class)));
        $this->app->alias(Atlas::class, 'atlas');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atlas');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Blade::componentNamespace('Atlas\\View\\Components', 'atlas');

        // Secure by default: only the local environment may use the editor
        // until the application defines its own `useAtlas` gate.
        if (! Gate::has('useAtlas')) {
            Gate::define('useAtlas', fn ($user = null) => $this->app->environment('local'));
        }

        $this->registerBlocks();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, MakeBlockCommand::class]);

            $this->publishes([__DIR__.'/../config/atlas.php' => config_path('atlas.php')], 'atlas-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/atlas')], 'atlas-views');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'atlas-migrations');
        }
    }

    protected function registerBlocks(): void
    {
        $atlas = $this->app->make(Atlas::class);

        $builtin = [
            Builtin\Section::class, Builtin\Columns::class, Builtin\Heading::class,
            Builtin\Text::class, Builtin\Image::class, Builtin\Button::class,
            Builtin\Spacer::class, Builtin\Divider::class,
        ];

        if (config('atlas.custom_code')) {
            $builtin[] = Builtin\CustomCode::class;
        }
        if (config('atlas.allow_blade_code')) {
            $builtin[] = Builtin\BladeCode::class;
        }

        foreach ($builtin as $class) {
            $atlas->block($class);
        }

        foreach ((array) config('atlas.blocks', []) as $class) {
            $atlas->block($class);
        }

        $this->discoverBlocks($atlas);
    }

    /** Auto-register every Block subclass found in app/Atlas/Blocks. */
    protected function discoverBlocks(Atlas $atlas): void
    {
        $path = config('atlas.discover.path') ?? app_path('Atlas/Blocks');
        $namespace = config('atlas.discover.namespace') ?? $this->app->getNamespace().'Atlas\\Blocks';

        if (! is_dir($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = rtrim($namespace, '\\').'\\'.$relative;

            if (class_exists($class)
                && is_subclass_of($class, Block::class)
                && ! (new ReflectionClass($class))->isAbstract()) {
                $atlas->block($class);
            }
        }
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('atlas.path'),
            'middleware' => config('atlas.middleware', ['web']),
            'as' => 'atlas.',
        ], function () {
            // Editor assets are static and public.
            Route::get('assets/{file}', [Http\Controllers\AssetController::class, 'show'])
                ->where('file', 'atlas\.(js|css)')->name('asset');

            Route::middleware(Authorize::class)->group(__DIR__.'/../routes/web.php');
        });

        // Registered after every other route so the app always wins.
        $this->app->booted(function () {
            if (! config('atlas.frontend.enabled')) {
                return;
            }

            $prefix = trim((string) config('atlas.frontend.prefix'), '/');

            Route::middleware(config('atlas.frontend.middleware', ['web']))->group(function () use ($prefix) {
                if ($prefix === '' && config('atlas.frontend.home')) {
                    Route::get('/', [FrontendController::class, 'home'])->name('atlas.home');
                }

                Route::get(trim($prefix.'/{slug}', '/'), [FrontendController::class, 'show'])
                    ->where('slug', '[a-z0-9]+(?:[\-\/][a-z0-9]+)*')
                    ->name('atlas.page');
            });
        });
    }
}
