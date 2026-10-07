<?php

declare(strict_types=1);

namespace Atlas\Support;

use Atlas\Models\Page;
use Illuminate\Http\Request;

class Locales
{
    /** Configured locales: code => native name. */
    public static function available(): array
    {
        $locales = (array) config('atlas.locales', []);

        return $locales ?: [self::default() => strtoupper(self::default())];
    }

    public static function default(): string
    {
        return (string) (config('atlas.default_locale') ?: config('app.locale', 'en'));
    }

    public static function current(): string
    {
        return app()->getLocale();
    }

    public static function multiple(): bool
    {
        return count(self::available()) > 1;
    }

    public static function isRtl(?string $locale = null): bool
    {
        return in_array(substr($locale ?? self::current(), 0, 2), ['ar', 'he', 'fa', 'ur'], true);
    }

    public static function prefixed(): bool
    {
        return (bool) config('atlas.frontend.locale_prefix');
    }

    /** Public URL of a page in a given locale. */
    public static function url(Page $page, ?string $locale = null, bool $explicit = false): string
    {
        $locale ??= self::current();
        $prefix = trim((string) config('atlas.frontend.prefix'), '/');
        $segments = [$prefix];

        if (self::prefixed() && $locale !== self::default()) {
            $segments[] = $locale;
        }

        $url = url(trim(implode('/', array_filter([...$segments, $page->slug])), '/'));

        // Switcher links must say ?lang= even for the default locale, or the remembered locale would win.
        if (! self::prefixed() && ($explicit || $locale !== self::default())) {
            $url .= '?lang=' . $locale;
        }

        return $url;
    }

    /** Links for the language switcher. */
    public static function links(): array
    {
        $page = request()->attributes->get('atlas.page');

        return collect(self::available())->map(function ($name, $code) use ($page) {
            if ($page instanceof Page) {
                $url = self::url($page, $code, explicit: true);
            } else {
                $url = request()->fullUrlWithQuery(['lang' => $code]);
            }

            return ['code' => $code, 'name' => $name, 'url' => $url, 'active' => $code === self::current()];
        })->values()->all();
    }

    /** Make $locale (if configured) the active locale; returns the one applied. */
    public static function apply(?string $locale): string
    {
        if ($locale && array_key_exists($locale, self::available())) {
            app()->setLocale($locale);
        }

        return app()->getLocale();
    }

    /** Decide the visitor's locale: URL prefix → ?lang → session → default. */
    public static function resolve(Request $request, ?string $routeLocale = null): string
    {
        $available = array_keys(self::available());
        $locale = self::default();

        if (self::prefixed()) {
            if ($routeLocale && in_array($routeLocale, $available, true)) {
                $locale = $routeLocale;
            }
        } else {
            $query = $request->query('lang');
            $session = $request->hasSession() ? $request->session()->get('atlas_locale') : null;

            if (is_string($query) && in_array($query, $available, true)) {
                $locale = $query;
                if ($request->hasSession()) {
                    $request->session()->put('atlas_locale', $query);
                }
            } elseif (is_string($session) && in_array($session, $available, true)) {
                $locale = $session;
            }
        }

        app()->setLocale($locale);

        return $locale;
    }
}
