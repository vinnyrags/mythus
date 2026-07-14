<?php

declare(strict_types=1);

namespace Mythus\Hooks;

use Mythus\Contracts\CacheDriver;
use Mythus\Contracts\Hook;
use Mythus\Support\Cache\CacheContext;
use Throwable;
use WP_Post;

/**
 * Abstract dispatcher: invalidates downstream caches when content changes.
 *
 * A site subclasses this and returns its CacheDriver instances from drivers().
 * The hook is fully INERT when drivers() is empty — it binds no WordPress
 * actions at all — so the platform ships it dormant and each consumer opts in
 * by subclassing and listing the subclass in a provider's $hooks.
 *
 * Behaviour:
 *  - fires on save_post, acf/save_post, transition_post_status (covers scheduled
 *    / programmatic publishes that skip save_post), before_delete_post, trashed_post;
 *  - skips autosaves, revisions, and no-op status transitions;
 *  - gates to postTypes() when non-empty;
 *  - coalesces every trigger for the same post within one request into a SINGLE
 *    dispatch (editor saves fire save_post AND acf/save_post; a trash fires a
 *    transition AND trashed_post);
 *  - isolates driver failures (a throwing driver never blocks the save or the
 *    sibling drivers), mirroring Provider::registerHooks().
 *
 * Additional drivers may be appended by any code via the 'mythus/cache_drivers'
 * filter — an escape hatch layered on top of drivers(), not the primary path.
 */
abstract class CacheInvalidation implements Hook
{
    /** Post types that never represent front-end content. */
    private const IGNORED_TYPES = ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset'];

    /** @var CacheDriver[] */
    private array $drivers = [];

    /** @var array<string, true> Per-request de-dupe keyed by "postType|postId". */
    private array $seen = [];

    /**
     * The drivers this site dispatches to. Empty keeps the hook inert.
     *
     * @return CacheDriver[]
     */
    abstract protected function drivers(): array;

    /**
     * Post types to act on. Empty means "all".
     *
     * @return string[]
     */
    protected function postTypes(): array
    {
        return [];
    }

    public function register(): void
    {
        /** @var mixed $drivers */
        $drivers = apply_filters('mythus/cache_drivers', $this->drivers(), $this);
        $this->drivers = array_values(array_filter(
            is_array($drivers) ? $drivers : [],
            static fn ($driver): bool => $driver instanceof CacheDriver,
        ));

        if ($this->drivers === []) {
            return; // fully inert — bind nothing
        }

        add_action('save_post', [$this, 'onSave'], 20, 1);
        add_action('acf/save_post', [$this, 'onSave'], 20, 1);
        add_action('transition_post_status', [$this, 'onTransition'], 10, 3);
        add_action('before_delete_post', [$this, 'onDelete'], 10, 1);
        add_action('trashed_post', [$this, 'onTrash'], 10, 1);
    }

    /**
     * @param int|string $postId acf/save_post passes non-numeric ids ('options')
     *                           for options pages; (int) casts those to 0.
     */
    public function onSave(int|string $postId): void
    {
        $this->maybeDispatch('save', (int) $postId);
    }

    public function onTransition(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($newStatus === $oldStatus || $newStatus === 'trash') {
            return; // no-op; trash is handled by trashed_post
        }

        $this->maybeDispatch('status_change', (int) $post->ID, $oldStatus, $newStatus);
    }

    public function onDelete(int|string $postId): void
    {
        $this->maybeDispatch('delete', (int) $postId);
    }

    public function onTrash(int|string $postId): void
    {
        $this->maybeDispatch('trash', (int) $postId);
    }

    private function maybeDispatch(string $event, int $postId, ?string $oldStatus = null, ?string $newStatus = null): void
    {
        if ($postId <= 0) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        $postType = get_post_type($postId);
        if ($postType === false || in_array($postType, self::IGNORED_TYPES, true)) {
            return;
        }

        $status = get_post_status($postId);
        if ($status === 'auto-draft' || $status === 'inherit') {
            return;
        }

        $allowed = $this->postTypes();
        if ($allowed !== [] && !in_array($postType, $allowed, true)) {
            return;
        }

        // Coalesce all triggers for the same post within one request.
        $key = $postType . '|' . $postId;
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;

        $this->dispatch(new CacheContext($event, $postId, $postType, $oldStatus, $newStatus));
    }

    private function dispatch(CacheContext $context): void
    {
        foreach ($this->drivers as $driver) {
            try {
                $driver->invalidate($context);
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'CacheInvalidation: driver %s failed: %s',
                    $driver::class,
                    $e->getMessage(),
                ));
            }
        }
    }
}
