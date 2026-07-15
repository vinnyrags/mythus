# Upgrading Mythus

Mythus follows [Semantic Versioning](https://semver.org/). Minor and patch
releases are backward-compatible; breaking changes only land in major releases
and are called out here with a migration checklist.

## From 1.1.x to 1.2.0

**Removes the cache-invalidation seam** (`CacheDriver`, `CacheContext`,
`RevalidationWebhookDriver`, `CacheInvalidation`). Action is only required if a theme
adopted it — i.e. subclassed `Mythus\Hooks\CacheInvalidation` and registered the
subclass in a provider's `$hooks`:

1. Remove the subclass from `$hooks` and delete the subclass file.
2. Drop any `*_REVALIDATION_SECRET` define the driver relied on.
3. If you still want save-triggered revalidation, do it in the theme (a small
   `Hook` that fires `wp_remote_post` — mirror `ActivityWebhook`), or reintroduce the
   seam deliberately.

A theme that never adopted it needs no changes.

## From 1.0.x to 1.1.0

**No action required.** 1.1.0 is purely additive. The new cache-invalidation
seam (`CacheDriver`, `CacheContext`, `CacheInvalidation`, `RevalidationWebhookDriver`)
is **inert by default** — the abstract `CacheInvalidation` hook binds no WordPress
actions unless a theme subclasses it and returns a non-empty `drivers()`. A site
that does nothing sees no behavioural change.

### Opting in (a headless-front-end consumer)

1. Subclass `Mythus\Hooks\CacheInvalidation` in your theme, narrow `postTypes()`
   to the post types your front-end renders, and return a
   `RevalidationWebhookDriver` from `drivers()`:

   ```php
   final class MyCacheInvalidation extends \Mythus\Hooks\CacheInvalidation
   {
       protected function postTypes(): array
       {
           return ['card', 'product'];
       }

       protected function drivers(): array
       {
           $secret = defined('MY_REVALIDATION_SECRET') ? (string) MY_REVALIDATION_SECRET : '';

           return [
               new \Mythus\Support\Cache\RevalidationWebhookDriver(
                   endpoint: 'https://front-end.example/api/revalidate',
                   secret: $secret,
                   postTypePathMap: [
                       'card'    => ['/', '/cards'],
                       'product' => ['/', '/shop'],
                   ],
               ),
           ];
       }
   }
   ```

2. Register the subclass in a provider's `$hooks` array (always-on, additive).
3. Define the secret **per environment** (e.g. in `wp-config-env.php`), matching
   the front-end's own revalidation secret. Leave it undefined anywhere you want
   the driver to stay inert (e.g. local dev). The webhook is non-blocking, so a
   slow or unreachable endpoint never delays an editor save.

### Rolling back

Re-pin the `vincentragosta/mythus` constraint to your previous version and run
`composer update vincentragosta/mythus`. Because the seam is inert unless a
subclass is registered, removing the subclass from `$hooks` also fully disables
it without a version change.
