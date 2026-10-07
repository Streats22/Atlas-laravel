<?php

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
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'disk' => 'public',
        'directory' => 'atlas',
        'max_kb' => 5120,
    ],
];
