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
use Atlas\Http\Controllers\SitemapController;
use Atlas\Http\Middleware\Authorize;
use Atlas\Models\CustomBlock;
use Atlas\Packaging\MediaUrls;
use Atlas\Support\Features;
use Atlas\Support\Locales;
use Atlas\Support\SlugPolicy;
use Atlas\Templates\BlankTemplate;
use Atlas\Templates\LandingTemplate;
use Atlas\Templates\PortfolioTemplate;
use Atlas\Templates\TemplateRegistry;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/atlas.php', 'atlas');

        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(Atlas::class, fn ($app) => new Atlas($app->make(BlockRegistry::class), $app->make(TemplateRegistry::class)));
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
        $this->registerTemplates();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, MakeBlockCommand::class, ListBlocksCommand::class, DemoCommand::class, ExportCommand::class, ImportCommand::class, PackageCommand::class]);

            $this->publishes([__DIR__ . '/../config/atlas.php' => config_path('atlas.php')], 'atlas-config');
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/atlas')], 'atlas-views');
            $this->publishes([__DIR__ . '/../lang' => $this->app->langPath('vendor/atlas')], 'atlas-lang');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'atlas-migrations');
        }
    }

    protected function registerTemplates(): void
    {
        $templates = $this->app->make(TemplateRegistry::class);

        foreach ([BlankTemplate::class, LandingTemplate::class, PortfolioTemplate::class] as $template) {
            $templates->register($template);
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

        if (Features::customCode()) {
            $builtin[] = Builtin\CustomCode::class;
        }
        if (Features::bladeCode()) {
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
            if (! Features::customCode()) {
                return;
            }

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
        $atlas->discover(
            config('atlas.discover.path') ?? app_path('Atlas/Blocks'),
            config('atlas.discover.namespace') ?? $this->app->getNamespace() . 'Atlas\\Blocks',
        );
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

        // Public page routes are added at routing time, i.e. after every application route has been
        // loaded, so the app always wins (e.g. its own "/"); Atlas only answers what nothing else matches.
        $registered = false;
        $this->app['events']->listen(Routing::class, function () use (&$registered): void {
            if (! $registered) {
                $registered = true;
                $this->registerFrontendRoutes();
            }
        });
    }

    protected function registerFrontendRoutes(): void
    {
        if (! config('atlas.frontend.enabled')) {
            return;
        }

        $prefix = trim((string) config('atlas.frontend.prefix'), '/');

        Route::middleware(config('atlas.frontend.middleware', ['web']))->group(function () use ($prefix) {
            // /{atlasLocale} and /{atlasLocale}/{atlasSlug} for non-default locales (must come before the generic slug route)
            $others = array_map('preg_quote', array_values(array_diff(array_keys(Locales::available()), [Locales::default()])));
            if (Locales::prefixed() && $others) {
                $pattern = implode('|', $others);
                Route::get(trim($prefix . '/{atlasLocale}', '/'), [FrontendController::class, 'homeLocale'])
                    ->where('atlasLocale', $pattern)->name('atlas.home.locale');
                Route::get(trim($prefix . '/{atlasLocale}/{atlasSlug}', '/'), [FrontendController::class, 'showLocale'])
                    ->where('atlasLocale', $pattern)->where('atlasSlug', SlugPolicy::ROUTE_PATTERN)->name('atlas.page.locale');
            }

            // Laravel keys routes by URI, so registering a URI the app already owns would silently replace it.
            if (config('atlas.frontend.sitemap') && ! $this->appDefines('sitemap.xml')) {
                Route::get('sitemap.xml', SitemapController::class)->name('atlas.sitemap');
            }

            if ($prefix === '' && config('atlas.frontend.home') && ! $this->appDefines('/')) {
                Route::get('/', [FrontendController::class, 'home'])->name('atlas.home');
            }

            Route::get(trim($prefix . '/{atlasSlug}', '/'), [FrontendController::class, 'show'])
                ->where('atlasSlug', SlugPolicy::ROUTE_PATTERN)
                ->name('atlas.page');
        });
    }

    /** Does the application already have a GET route for this URI? */
    private function appDefines(string $uri): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array('GET', $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }
}
