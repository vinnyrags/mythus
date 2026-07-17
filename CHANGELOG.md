# Changelog

All notable changes to Mythus are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Mythus adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Versions are derived
from annotated git tags — there is no `version` field in `composer.json`.

## [Unreleased]

## [1.3.0] - 2026-07-17

### Added

- **One-way DB + uploads sync engine** — `Mythus\Support\Sync\{SyncEnv,
  SyncCommand, SyncCommandBuilder}`. Pulls a higher environment's database and
  uploads DOWN into the current (lower) environment via the
  `wp mythus sync-from <staging|production>` CLI command. Downhill-only by
  construction (`local ← staging/production`, `staging ← production`,
  `production ← none`) so production is never a sync target.

  - **Staleness guard** in the engine (not per-site): compares the newest
    `post_modified_gmt` on each side and refuses to pull a source that is BEHIND
    the current env — which would silently roll it back and discard newer edits
    (e.g. clicking "Pull from Production" pre-launch while staging is still the
    source of truth). The admin buttons never pass `--force`, so an accidental
    click is blocked; a deliberate rollback is CLI-only (`--force`). Fails open
    when either clock can't be read.
  - **Exec-env hardening** (`SyncCommandBuilder`): pins `cd`/`PATH`/`HOME` ahead
    of the wp-cli invocation so launched subprocesses survive PHP-FPM's
    `clear_env=yes` and inaccessible worker CWDs (the `posix_spawn()`/`sh:
    Permission denied` regression). Shell-escapes source/actor/paths.
  - **Inert by default**: sources and cache dirs come from the
    `mythus/sync/sources` and `mythus/sync/cache_dirs` filters, so a site that
    doesn't configure Sync has no valid pull target and the command errors out
    cleanly. Snapshots the target before importing (keeps newest 3), rewrites
    URLs serialization-safe, re-asserts non-prod `noindex`, flushes caches, and
    records an activity log under the web-blocked `.mythus-sync` uploads dotdir.

  Generalized from the AVFTB-native Sync provider so every property inherits the
  same engine (and the staleness guard MF previously lacked). The admin UI that
  drives it ships separately in arthouse-kit; child themes supply only their
  droplet config via the two filters.

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
