# AGENTS.md

Guidance for AI coding agents (and humans) working on **Atlas**, a visual drag & drop page builder package for Laravel 12/13. Read this before changing code. `CLAUDE.md` adds Claude-specific workflow notes.

## What this repository is

A Composer package (`streats22/atlas`, namespace `Atlas\`), **not** a Laravel app. It ships:

* A block-tree page model (`atlas_pages`) rendered server-side by Blade views.
* A dependency-free editor (`resources/dist/atlas.js` + `atlas.css`) and page runtime (`runtime.js`, `base.css`). **There is no build step** — edit those files directly.
* Artisan commands, a block builder, translations, theming and a packaging pipeline.

## Commands

```bash
composer install
vendor/bin/phpunit                  # full suite (PHP 8.3+, Orchestra Testbench)
composer lint                       # Pint, PSR-12 (CI runs this)
composer fix                        # auto-fix style
node --check resources/dist/atlas.js && node --check resources/dist/runtime.js
vendor/bin/testbench serve --port=8765   # dev server with sample data: vendor/bin/testbench atlas:demo
```

Always run `composer lint` and `vendor/bin/phpunit` before finishing. Both must be green.

## Architecture map

| Path | Responsibility |
|------|----------------|
| `src/AtlasServiceProvider.php` | Wiring only: config, views, lang, routes, commands, built-in block registration |
| `src/Atlas.php` (+ `Facades/Atlas`) | Public API: `block()`, `viewBlock()`, `style()`, `script()`, `auth()`, `page()` |
| `src/Blocks/` | `Block` (abstract base), `Field` (field builders), `BlockRegistry` (lazy), `DbBlock`, `FieldNormalizer`, `CommonFields`; `Builtin/*` = shipped blocks |
| `src/Rendering/` | `Renderer` (tree → HTML), `NodeWrapper`, `PropLocalizer`, `DocumentBuilder`, `AssetBag`, `PageData`, `RenderNode` |
| `src/Support/` | `Tree` (sanitise), `Url` (safe links), `Theme` + `PageMeta` (value objects), `Locales`, `Template` (builder template language), `ViewHelpers` |
| `src/Enums/` | `PageStatus`, `ThemeMode`, `Spacing` |
| `src/Templates/` | `PageTemplate` (extend to add start-from templates), `NodeFactory`, `TemplateRegistry`; `Blank/Landing/PortfolioTemplate` |
| `src/Packaging/` | `Bundle*`, `MediaUrls`, `PackageScaffolder`, `PackageSpec` behind `atlas:export/import/package` |
| `src/Http/` | Thin controllers, `Requests/*` (validation), `Middleware/Authorize` |
| `resources/views/blocks/*.blade.php` | One view per built-in block |
| `resources/dist/` | `atlas.js`/`atlas.css` (editor), `base.css`/`runtime.js` (public pages), `canvas.css` (editor canvas only) |
| `lang/{en,nl}/` | `ui` (editor strings), `blocks`, `fields`, `options`, `categories` |
| `stubs/` | Scaffolding templates (`atlas:make-block`, `atlas:package`) |

Request flow: editor → `PUT /atlas/api/pages/{page}` (`SavePageRequest`) → `Tree::sanitize` → `pages.content` JSON. Public: `FrontendController` → `Page::render()` → `Renderer::layoutData()` → layout view.

## Coding standards (enforced)

* **PSR-12** via Pint (`pint.json`), plus `declare(strict_types=1)` in every PHP file. Run `composer fix`.
* **OOP**: small single-purpose classes, constructor injection, `final` for value objects/services not meant to be extended, `readonly` properties, enums instead of magic strings (`PageStatus`, `ThemeMode`, `Spacing`), typed signatures and return types everywhere.
* **Thin controllers** — validation lives in `FormRequest`s, logic in services/value objects.
* **DRY**: reuse `Field`/`CommonFields` for inspector fields, `ViewHelpers` (`$pick`, `$int`, `$aspect`, `$safe`) in views instead of re-implementing whitelists, `PageMeta`/`Theme` instead of reading raw arrays.
* **Extension points**: new blocks extend `Block` (or `BuiltinBlock` for shipped ones); never special-case a block type in `Renderer`.
* Comments explain *why*, not *what*. Keep public API stable; note breaking changes in `CHANGELOG.md`.

## Security rules (non-negotiable)

1. **SQL injection safety** — use Eloquent/query-builder with bound parameters only. **Never** use `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, `DB::statement` etc. with user input; `SecurityTest` greps `src/` and fails on raw SQL APIs. Models use `$fillable`, never `$guarded = []`.
2. **Output escaping** — Blade `{{ }}` for all props. `{!! !!}` only for already-sanitised HTML (`$children`, Markdown output with `html_input => strip`, trusted code blocks).
3. **Whitelist values** that end up in class names/styles/attributes with `$pick()`/`$int()`/`$aspect()`/`$cssColor()`/`$cssLength()`; run links through `$safe()` and anything inside CSS `url('…')` through `$cssUrl()`. Never echo a raw prop into a `style=""` attribute.
4. **Raw code is a feature, not a bug** — ask `Features::customCode()` / `Features::bladeCode()` (never `config()` directly), and make every code path honour it, including database-backed blocks — (Custom Code block, page JS/CSS, `{{{ }}}` in builder templates) — but only behind the `useAtlas` gate and `atlas.custom_code`. `allow_blade_code` stays **off** by default.
5. Editor routes always pass through `Authorize` (gate `useAtlas`, denied outside `local` by default).
6. Zip/bundle imports must stay path-traversal safe (see `BundleArchive`, `MediaUrls::isSafeName`) and must apply the same validation as the editor (`SlugPolicy`, `PageMeta::sanitize`).
7. Never register a route URI the host app might own: Laravel keys routes by URI, so a second `GET /` silently **replaces** the app's. Atlas registers public routes at routing time, skips URIs the app defines, and uses unique parameter names (`atlasSlug`, `atlasLocale`).

## Testing rules

* Every behaviour change gets a test in `tests/Feature` or `tests/Unit` (PHPUnit + Orchestra Testbench; DB is in-memory SQLite).
* `LibraryTest::test_every_builtin_block_renders_with_its_defaults_in_both_modes` renders **every** registered block in public and editor mode — new blocks are covered automatically; keep it passing.
* Test hostile input for anything new that touches the database, URLs, HTML or files.
* Browser-level checks (drag & drop, dark mode) use Playwright against `vendor/bin/testbench serve`; see `CLAUDE.md`.

## Adding things

* **A built-in block**: class in `src/Blocks/Builtin/` extending `BuiltinBlock` (type, label, icon, category, `fields()`, optional `data()`/`assets()`), view `resources/views/blocks/{type}.blade.php`, register in `AtlasServiceProvider::registerBlocks()`, CSS in `resources/dist/base.css` (use `--atlas-*` tokens; support light **and** dark), labels in `lang/nl/blocks.php`, field/option labels in `lang/nl/{fields,options}.php`, add to the README table.
* **A field type**: `Field::*` builder, `FieldNormalizer` (builder), `fieldEl()` in `atlas.js`.
* **An editor string**: add to `lang/en/ui.php` **and** `lang/nl/ui.php`; use `t('key')` in JS or `__('atlas::ui.key')` in PHP. `TranslationsTest` fails if a key used in code is missing or the two files drift.
* **A page template**: extend `PageTemplate`, register in `AtlasServiceProvider::registerTemplates()` (or `Atlas::template()` for apps), add `template_{key}` / `template_{key}_desc` to both `lang/*/ui.php`.
* **A config option**: `config/atlas.php` with a comment, README config table, CHANGELOG.

## Spacing & design conventions

Spacing is driven by CSS variables (`--atlas-space`, `--atlas-section-y`, `--atlas-gutter`) set from the page's *Spacing* setting. Block spacing rules live in the "Spacing system" section of `base.css`; prefer `:where()` for zero-specificity rhythm rules so a block's own margin wins. Never hard-code colours in block CSS — use `--atlas-*` variables so dark mode works.

## Git

Small, focused commits with imperative messages. Do not commit `vendor/`, `composer.lock` or generated media. Do not open a PR unless asked.
