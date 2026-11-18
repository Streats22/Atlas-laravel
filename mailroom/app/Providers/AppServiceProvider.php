<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MailConfig;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(MailConfig::class);
    }

    public function boot(): void
    {
        $this->app->make(MailConfig::class)->apply();

        // Throttle campaign delivery to the provider's limit (0 = unlimited).
        RateLimiter::for('campaign-mail', function () {
            $perMinute = $this->app->make(MailConfig::class)->perMinute();

            return $perMinute > 0 ? Limit::perMinute($perMinute) : Limit::none();
        });
    }
}
