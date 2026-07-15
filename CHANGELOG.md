# Changelog

All notable changes to Mythus are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Mythus adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Versions are derived
from annotated git tags — there is no `version` field in `composer.json`.

## [Unreleased]

## [1.2.0] - 2026-07-14

### Removed

- **The cache-invalidation seam** added in 1.1.0 — `Mythus\Contracts\CacheDriver`,
  `Mythus\Support\Cache\CacheContext`, `Mythus\Support\Cache\RevalidationWebhookDriver`,
  and `Mythus\Hooks\CacheInvalidation` (and their tests).

  Rationale: a cross-property audit found **no consumer needs an event-driven cache
  seam**. The arthouse marketing sites use a 30s nginx FastCGI microcache (TTL is the
  correctness floor; a short-TTL microcache captures the same origin protection as
  long-TTL+purge exactly when traffic warrants it, without purge-coverage risk or a
  plugin dependency). vincentragosta.io/itzenzo revalidate at the sync-script layer,
  where the real content changes originate; the `save_post` webhook only fired on
  rarely-used manual wp-admin edits. The seam generalized the old `PurgePageCache`
  hook, but the microcache pivot removed the thing it generalized. Removed rather than
  carried as dormant speculative code. If a future headless consumer needs
  save-triggered revalidation, it can be reintroduced deliberately.

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

[Unreleased]: https://github.com/vinnyrags/mythus/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/vinnyrags/mythus/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/vinnyrags/mythus/compare/v1.0.1...v1.1.0
