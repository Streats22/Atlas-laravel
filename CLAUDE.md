# CLAUDE.md

@AGENTS.md

Everything in `AGENTS.md` applies. Additional notes for Claude Code:

## Workflow

1. Read the relevant files before editing; keep changes minimal and in the existing style.
2. After PHP changes: `composer fix && vendor/bin/phpunit`. After JS/CSS changes: `node --check resources/dist/*.js` and look at it in a browser (below).
3. Never weaken a test or a security rule to get green. If `SecurityTest` fails, remove the raw SQL, don't edit the test.
4. Update `README.md`, `CHANGELOG.md` and `lang/nl/*` together with the code they describe.

## Verifying UI changes in a real browser

```bash
export COMPOSER_ALLOW_SUPERUSER=1
vendor/bin/testbench package:create-sqlite-db && vendor/bin/testbench migrate --force
vendor/bin/testbench atlas:demo --force
(nohup vendor/bin/testbench serve --port=8765 >/tmp/serve.log 2>&1 &)
# Playwright + Chromium are preinstalled: /opt/pw-browsers (do not run `playwright install`)
```

* Editor: `http://localhost:8765/atlas` (open in `local`, gate allows it). Public demo: `/demo`, `/demo?lang=nl`.
* `testbench.yaml` sets `ATLAS_LOCALES="en:English,nl:Nederlands"` so translations can be exercised.
* Check **both** colour schemes (`colorScheme: 'light' | 'dark'`) and a 390px-wide viewport (no horizontal overflow).
* Drag & drop is pointer-based (mousedown → move ≥5px → mouseup); drive it with `page.mouse` rather than Playwright's `dragTo`.
* Don't `pkill -f` a pattern that appears in your own command line; use `pgrep -f "[p]hp -S" | xargs kill`.

## Gotchas

* **Route URIs collide silently**: `RouteCollection` keys by `domain.uri`, so adding `GET /` again replaces an existing route regardless of order. Check `appDefines()` before registering fixed URIs and keep Atlas route parameter names unique.
* **Composer path repositories key on the git commit** — commit before `composer update streats22/atlas` in a test app, or the old code is re-used.
* Prefer `ss -ltnp | grep :PORT` to stop dev servers; `pkill -f` patterns match your own shell.
* `app()->setLocale()` overwrites `config('app.locale')`, so the default content locale is pinned in `AtlasServiceProvider::boot()` (`atlas.default_locale`). Always use `Locales::default()`, never `config('app.locale')`.
* Laravel passes route parameters to controller methods **positionally** — give locale routes their own methods (`homeLocale`, `showLocale`).
* `BlockRegistry` loads database blocks lazily on first use; call `all()/has()/get()/definitions()` (they boot it) rather than reading internals.
* The editor canvas is a sandboxed iframe (`allow-same-origin`, no scripts): scripts/animations only run in Preview and on public pages.
* Blade echo syntax can't contain the literal `{{selector}}`; CSS scoping is done in PHP (`Renderer::scopeCss`).

## Packaging a site

`php artisan atlas:package vendor/name` scaffolds a publishable package from `app/Atlas/Blocks`, `resources/views/atlas`, `lang/vendor/atlas` and an exported bundle of pages / builder blocks / media. Use `--dry-run` first. Tests: `tests/Feature/PackagingTest.php`.
