<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Editor path & middleware
    |--------------------------------------------------------------------------
    | The visual editor lives at /{path}. Access is controlled by the
    | `useAtlas` gate (see README). By default only the "local" environment
    | may use the editor, so nothing is exposed in production until you
    | define the gate yourself.
    */
    'path' => 'atlas',
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Public pages
    |--------------------------------------------------------------------------
    | Published pages are served at /{prefix}/{slug}. Leave the prefix empty to
    | serve pages from the site root, WordPress style. The page whose slug
    | equals `home` is also served at "/" if no other route claims it.
    */
    'frontend' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'home' => 'home',
        'locale_prefix' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Page layout
    |--------------------------------------------------------------------------
    | A Blade view that wraps every page. It receives:
    |   $page, $content, $head, $scripts, $editing
    | Print $head inside <head>, $content inside <body> and $scripts just
    | before </body>. Point this at your own layout so pages use your theme,
    | Vite assets, fonts and so on.
    */
    'layout' => 'atlas::layouts.document',

    /*
    |--------------------------------------------------------------------------
    | Custom code
    |--------------------------------------------------------------------------
    | When enabled, editors can write raw HTML / CSS / JavaScript: the
    | "Custom Code" block, per-block CSS and page-level CSS/JS/<head> code.
    | Anyone with access to the editor is trusted with this power.
    */
    'custom_code' => true,

    /*
    |--------------------------------------------------------------------------
    | Blade code block
    |--------------------------------------------------------------------------
    | Compiles editor-written Blade (and therefore PHP) at render time. This is
    | arbitrary server-side code execution, so it is OFF by default. Only
    | enable it if every editor is a trusted developer.
    */
    'allow_blade_code' => false,

    /*
    |--------------------------------------------------------------------------
    | Developer blocks
    |--------------------------------------------------------------------------
    | Classes extending Atlas\Blocks\Block placed in `discover.path` are
    | registered automatically. Add more classes explicitly via `blocks`.
    | Leave path / namespace null to use app/Atlas/Blocks.
    */
    'discover' => [
        'path' => null,
        'namespace' => null,
    ],
    'blocks' => [],

    /*
    |--------------------------------------------------------------------------
    | Global assets
    |--------------------------------------------------------------------------
    | Stylesheets and scripts (URLs) loaded on every page and in the editor
    | canvas. Handy for CDN libraries such as Tailwind, Alpine or GSAP.
    */
    'assets' => [
        'styles' => [],
        'scripts' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales (translations)
    |--------------------------------------------------------------------------
    | Content locales editors can translate into: code => native name. With more
    | than one locale the editor shows a language switcher and every
    | "translatable" field can hold one value per locale. The default locale's
    | value is the fallback. `locale_prefix` (under `frontend`) serves
    | non-default locales at /{locale}/{slug}; otherwise ?lang=xx is used.
    */
    // Or set ATLAS_LOCALES="en:English,nl:Nederlands" in .env
    'locales' => array_column(
        array_map(fn ($pair) => array_pad(explode(':', $pair, 2), 2, ''), array_filter(explode(',', (string) env('ATLAS_LOCALES', '')))),
        1,
        0,
    ),
    'default_locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Theme (light / dark mode)
    |--------------------------------------------------------------------------
    | Defaults for every page; each page can override them in the editor.
    | mode: auto (follow the visitor's system) | light | dark
    | toggle: show a floating light/dark switch for visitors
    | light / dark: override any design token, e.g. ['bg' => '#fff', 'text' => '#111']
    */
    'theme' => [
        'default' => 'auto',
        'toggle' => false,
        'accent' => '#4f46e5',
        'accent_dark' => '#818cf8',
        'font' => 'system',
        'heading_font' => 'same',
        'light' => [],
        'dark' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN URLs used by animated blocks
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'lottie' => 'https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'disk' => 'public',
        'directory' => 'atlas',
        'max_kb' => 5120,
    ],
];
