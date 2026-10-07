# Atlas

A visual drag & drop page builder for Laravel 12 and 13 — with complete freedom to drop in your own code.

* **Visual editor** — drag blocks onto a live canvas, nest them in sections and columns, reorder by dragging, edit properties in an inspector, undo/redo, desktop/tablet/mobile preview.
* **Real custom code** — raw HTML/CSS/JS blocks, per-block scoped CSS, page-level CSS/JS/`<head>`, and *your own blocks* written as PHP classes + Blade views.
* **No build step** — the editor is dependency-free JavaScript served by the package. Nothing to compile.
* **Secure by default** — the editor is only reachable in the `local` environment until you define who may use it.

## Install

```bash
composer require streats22/atlas
php artisan atlas:install     # publishes config/atlas.php and runs the migration
```

Open **`/atlas`**, create a page, and start dragging. Pages you publish are served at `/{slug}` (the page with slug `home` is served at `/` if your app has no `/` route).

## Who can use the editor?

Atlas checks the `useAtlas` gate. Out of the box it allows the `local` environment only. In production define it yourself:

```php
// AppServiceProvider::boot()
use Atlas\Facades\Atlas;

Atlas::auth(fn ($user) => $user?->is_admin);
// or: Gate::define('useAtlas', fn ($user) => $user?->hasRole('editor'));
```

Everyone with editor access is trusted to write raw HTML/JS (see *Custom code*). Never grant it to untrusted users.

## Custom code

### 1. The Custom Code block
Drag **Custom Code** (Developer group) onto the page. It has three editors:

| Field | Behaviour |
|-------|-----------|
| HTML | Output exactly as written. |
| CSS | Output in a `<style>` tag. Write `{{selector}}` to target this block only: `{{selector}} h2 { color: red }`. |
| JavaScript | Runs on the live page and in Preview. By default wrapped in its own scope with `el` set to the block's element; untick *Isolate* for plain global script. |

Every block also has **CSS classes**, **HTML id** and **Custom CSS** under *Advanced*.

### 2. Page-level code
The **Page code** tab holds page CSS, page JavaScript and extra `<head>` HTML (meta tags, analytics, fonts…).

### 3. Your own blocks (full PHP + Blade)
```bash
php artisan atlas:make-block PricingTable
```
creates `app/Atlas/Blocks/PricingTable.php` and `resources/views/atlas/blocks/pricing-table.blade.php`. Blocks in `app/Atlas/Blocks` are discovered automatically.

```php
class PricingTable extends Block
{
    public function type(): string { return 'pricing-table'; }

    public function fields(): array
    {
        return [
            Field::text('heading', 'Heading', 'Pricing'),
            Field::select('currency', ['usd' => 'USD', 'eur' => 'EUR']),
            Field::code('notes', 'Notes', '', 'html'),
        ];
    }

    // Run any PHP you like: Eloquent, APIs, caches…
    public function data(array $props): array
    {
        return ['plans' => Plan::active()->get()];
    }
}
```

```blade
{{-- resources/views/atlas/blocks/pricing-table.blade.php --}}
<h2>{{ $props['heading'] }}</h2>
@foreach($plans as $plan) … @endforeach
```

Available in a block view: `$props`, `$children` (for containers), `$node`, `$id`, `$domId`, `$editing`.

Make a block a **container** (`container(): true`) and print `{!! $children !!}` to let editors drop other blocks inside it. For something quick without a class:

```php
Atlas::viewBlock('hero', 'blocks.hero', 'Hero', [Field::text('title')], category: 'Marketing');
```

Field types: `text`, `textarea`, `number`, `select`, `color`, `checkbox`, `image` (with upload), `code`, `url`.

### 4. Blade code block (opt-in)
Set `'allow_blade_code' => true` to add a **Blade Code** block that compiles editor-written Blade/PHP on render. This is remote code execution for anyone with editor access — enable it only for trusted developers.

### 5. Libraries & your own layout
```php
Atlas::style('https://cdn.jsdelivr.net/npm/some-lib/dist/lib.css');
Atlas::script('https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js', defer: true);
```
(or `config('atlas.assets')`). Styles load in the editor canvas too. To use your own theme, set `atlas.layout` to a Blade view:

```blade
<html><head><title>{{ $title }}</title>@vite('resources/css/app.css'){{ $head }}</head>
<body>@include('partials.nav'){{ $content }}{{ $scripts }}</body></html>
```

Disable all raw-code features with `'custom_code' => false`.

## Embedding pages in your own views
```blade
<x-atlas::page slug="footer" />        {{-- content + page CSS/JS --}}
{!! Atlas::page('footer') !!}          {{-- content only --}}
```

## Notes on the editor canvas
The canvas is a sandboxed iframe containing the real server-rendered page, so what you see is what visitors get (including your layout and global CSS). Custom **JavaScript does not run in the canvas** (that keeps the editor safe from broken scripts); press **Preview ▶** to run the page for real.

Shortcuts: `Ctrl/⌘+S` save · `Ctrl/⌘+Z` undo · `Ctrl/⌘+Shift+Z` redo · `Ctrl/⌘+D` duplicate · `Del` delete · `Esc` deselect/cancel drag.

## Testing
```bash
composer install && vendor/bin/phpunit
```

## License
MIT
