<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Blocks\Block;
use Atlas\Blocks\BlockRegistry;
use Atlas\Blocks\Builtin;
use Atlas\Blocks\DbBlock;
use Atlas\Console\DemoCommand;
use Atlas\Console\ExportCommand;
use Atlas\Console\ImportCommand;
use Atlas\Console\InstallCommand;
use Atlas\Console\ListBlocksCommand;
use Atlas\Console\MakeBlockCommand;
use Atlas\Console\PackageCommand;
use Atlas\Http\Controllers\FrontendController;
use Atlas\Http\Middleware\Authorize;
use Atlas\Models\CustomBlock;
use Atlas\Packaging\MediaUrls;
use Atlas\Support\Locales;
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
        $this->mergeConfigFrom(__DIR__ . '/../config/atlas.php', 'atlas');

        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(Atlas::class, fn ($app) => new Atlas($app->make(BlockRegistry::class)));
        $this->app->alias(Atlas::class, 'atlas');
        $this->app->singleton(MediaUrls::class, fn () => MediaUrls::fromConfig());
    }

    public function boot(): void
    {
        // Pin the default content locale now: app()->setLocale() later overwrites config('app.locale').
        if (! config('atlas.default_locale')) {
            config(['atlas.default_locale' => config('app.locale', 'en')]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'atlas');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'atlas');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        Blade::componentNamespace('Atlas\\View\\Components', 'atlas');

        // Secure by default: only the local environment may use the editor
        // until the application defines its own `useAtlas` gate.
        if (! Gate::has('useAtlas')) {
            Gate::define('useAtlas', fn ($user = null) => $this->app->environment('local'));
        }

        $this->registerBlocks();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, MakeBlockCommand::class, ListBlocksCommand::class, DemoCommand::class, ExportCommand::class, ImportCommand::class, PackageCommand::class]);

            $this->publishes([__DIR__ . '/../config/atlas.php' => config_path('atlas.php')], 'atlas-config');
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/atlas')], 'atlas-views');
            $this->publishes([__DIR__ . '/../lang' => $this->app->langPath('vendor/atlas')], 'atlas-lang');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'atlas-migrations');
        }
    }

    protected function registerBlocks(): void
    {
        $atlas = $this->app->make(Atlas::class);

        $builtin = [
            Builtin\Section::class, Builtin\Columns::class, Builtin\Spacer::class, Builtin\Divider::class, Builtin\Accordion::class,
            Builtin\Heading::class, Builtin\Text::class, Builtin\Image::class, Builtin\Button::class, Builtin\IconBox::class,
            Builtin\Hero::class, Builtin\Video::class, Builtin\SocialLinks::class,
            Builtin\PortfolioGrid::class, Builtin\ProjectShowcase::class, Builtin\Testimonials::class, Builtin\Timeline::class,
            Builtin\Skills::class, Builtin\Logos::class, Builtin\Gallery::class,
            Builtin\Counter::class, Builtin\Typewriter::class, Builtin\Marquee::class, Builtin\Carousel::class, Builtin\Lottie::class,
            Builtin\LanguageSwitcher::class, Builtin\ThemeToggle::class,
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

        // Blocks made in the editor's block builder are loaded lazily from the database.
        $atlas->blocks()->lazy(function ($registry) {
            try {
                foreach (CustomBlock::query()->get() as $model) {
                    if (! $registry->has($model->type)) {
                        $registry->register(new DbBlock($model));
                    }
                }
            } catch (\Throwable) {
                // The table may not exist yet (before `php artisan migrate`).
            }
        });
    }

    /** Auto-register every Block subclass found in app/Atlas/Blocks. */
    protected function discoverBlocks(Atlas $atlas): void
    {
        $path = config('atlas.discover.path') ?? app_path('Atlas/Blocks');
        $namespace = config('atlas.discover.namespace') ?? $this->app->getNamespace() . 'Atlas\\Blocks';

        if (! is_dir($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = rtrim($namespace, '\\') . '\\' . $relative;

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

            Route::get('assets/demo/{file}', [Http\Controllers\AssetController::class, 'demo'])
                ->where('file', '[1-8]\.jpg')->name('asset.demo');

            Route::middleware(Authorize::class)->group(__DIR__ . '/../routes/web.php');
        });

        // Registered after every other route so the app always wins.
        $this->app->booted(function () {
            if (! config('atlas.frontend.enabled')) {
                return;
            }

            $prefix = trim((string) config('atlas.frontend.prefix'), '/');

            Route::middleware(config('atlas.frontend.middleware', ['web']))->group(function () use ($prefix) {
                // /{locale} and /{locale}/{slug} for non-default locales (must come before the generic slug route)
                $others = array_map('preg_quote', array_values(array_diff(array_keys(Locales::available()), [Locales::default()])));
                if (Locales::prefixed() && $others) {
                    $pattern = implode('|', $others);
                    Route::get(trim($prefix . '/{locale}', '/'), [FrontendController::class, 'homeLocale'])
                        ->where('locale', $pattern)->name('atlas.home.locale');
                    Route::get(trim($prefix . '/{locale}/{slug}', '/'), [FrontendController::class, 'showLocale'])
                        ->where('locale', $pattern)->where('slug', '[a-z0-9]+(?:[\-\/][a-z0-9]+)*')->name('atlas.page.locale');
                }

                if ($prefix === '' && config('atlas.frontend.home')) {
                    Route::get('/', [FrontendController::class, 'home'])->name('atlas.home');
                }

                Route::get(trim($prefix . '/{slug}', '/'), [FrontendController::class, 'show'])
                    ->where('slug', '[a-z0-9]+(?:[\-\/][a-z0-9]+)*')
                    ->name('atlas.page');
            });
        });
    }
}
