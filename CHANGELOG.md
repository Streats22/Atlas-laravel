# Changelog

All notable changes to Atlas are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
- Spacing system (`compact` / `comfortable` / `spacious`) with page gutters and block rhythm.
