# Changelog

All notable changes to Mythus are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Mythus adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Versions are derived
from annotated git tags — there is no `version` field in `composer.json`.

## [Unreleased]

## [1.1.0] - 2026-07-14

### Added

- **Cache-invalidation seam** — a theme-agnostic way to notify downstream caches
  when content changes, shipped **inert by default** (binds no WordPress hooks
  until a consumer opts in). All additive; no existing behaviour changes.
  - `Mythus\Contracts\CacheDriver` — `invalidate(CacheContext $context): void`.
  - `Mythus\Support\Cache\CacheContext` — immutable DTO describing a change
    (`event`, `postId`, `postType`).
  - `Mythus\Hooks\CacheInvalidation` — abstract `Hook`. A consumer subclasses it,
    returns its drivers from `drivers()`, and optionally narrows `postTypes()`.
    Fires on `save_post`, `acf/save_post`, `transition_post_status`,
    `before_delete_post`, `trashed_post`; skips autosaves/revisions/no-op
    transitions; gates to `postTypes()`; coalesces every trigger for one post
    within a request into a single dispatch; isolates driver failures
    (`try/catch → error_log`) so a throwing driver never blocks the save.
    **With no drivers, `register()` binds nothing.**
  - `Mythus\Support\Cache\RevalidationWebhookDriver` — POSTs affected paths to a
    headless front-end's revalidation endpoint (`{secret, paths[]}`), non-blocking
    (`blocking: false`, 2s timeout), inert when its secret is empty. Mirrors the
    established `ActivityWebhook` pattern.

[Unreleased]: https://github.com/vinnyrags/mythus/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/vinnyrags/mythus/compare/v1.0.1...v1.1.0
