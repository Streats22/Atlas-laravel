# Changelog

All notable changes to Atlas are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Mobile-friendly editor: on phones the panels become bottom sheets opened from a bottom bar, Save stays pinned, controls get touch-sized and block moving/dragging works with touch (pointer events).
- Floating Atlas toolbar on live pages (and draft previews) for users allowed by the `useAtlas` gate: status, "Edit page", "Pages", collapsible. Responses with it are `Cache-Control: private, no-store`. The editor's "View" now opens in the same tab (saving first) and shows the preview for drafts.
- Redesigned page list (cards, status pills, responsive).

### Changed
- Editor asset URLs carry a `?v=` version so upgrades are not hidden by the one-hour browser cache.

### Fixed (from an independent code review)
- Builder blocks no longer render raw HTML/JS when `custom_code` is disabled.
- CSS `url()` injection and unwhitelisted style values (`width`, colours, backgrounds) in block views are neutralised (`cssUrl`, `cssColor`, `cssLength`).
- Bundles: media paths are relative to the uploads directory and traversal-safe; imports validate slugs, meta and dates like the editor does; temp dirs are cleaned up.
- The language switcher can switch back to the default language; `atlas.theme.default` is honoured.
- Generated packages discover nested blocks and skip abstract ones, rewrite all block-namespace references, and always emit valid `composer.json`.
- Template engine ignores stray closing tags and reads `{{selector}}`/`{{children}}` from the root context only.
- Page creation/duplication never takes the reserved `atlas` slug.
- Atlas no longer replaces an application's own routes (`/`, `/sitemap.xml`, `/{slug}`): public routes are registered at routing time with collision-free parameter names.

### Added
- Visual drag & drop editor with live canvas, layers, inspector, undo/redo and responsive preview.
- 28 built-in blocks: layout, content, portfolio, animated and utility blocks.
- Light / dark / automatic themes with a visitor toggle, per-page accent colours, fonts and spacing.
- Content translations (`name@locale`), language switcher, locale-prefixed routes, English and Dutch editor UI.
- Entrance and hover animations with a reduced-motion-aware runtime.
- Custom code: Custom Code block, per-block CSS, page CSS/JS/head, PHP + Blade blocks, no-PHP block builder.
- `atlas:install`, `atlas:demo`, `atlas:make-block`, `atlas:blocks`, `atlas:export`, `atlas:import` and `atlas:package`.
- `atlas:export` / `atlas:import` bundles (zip with media, portable image URLs) and `atlas:package`, a smart packager that scaffolds a publishable Composer package from a site.
- Bundled sample artwork and a richer `atlas:demo` page.
- AGENTS.md / CLAUDE.md, PSR-12 (Pint) + strict types, CI workflow, SQL-injection safety tests.
- Editor: autosave recovery, copy/cut/paste, Layers drag & drop; page list search, pagination and duplicate.
- `/sitemap.xml` with hreflang alternates and `<link rel="canonical">`.
- Page templates (Blank, Landing, Portfolio) with an extensible `PageTemplate` API; `atlas:demo` now uses the Portfolio template.
- Translation-key guard test.
- Translatable accessibility labels on public pages (carousel, lightbox, switchers).
- `atlas:install` creates the `public/storage` link for uploads.
- Spacing system (`compact` / `comfortable` / `spacious`) with page gutters and block rhythm.
