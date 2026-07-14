<?php

declare(strict_types=1);

namespace Mythus\Tests\Unit\Hooks;

use Mythus\Contracts\CacheDriver;
use Mythus\Hooks\CacheInvalidation;
use Mythus\Support\Cache\CacheContext;
use WorDBless\BaseTestCase;

/**
 * Unit tests for the CacheInvalidation abstract dispatcher.
 *
 * Dispatch tests insert the post BEFORE registering the hook (simulating an edit
 * of pre-existing content) so the insert-time transition_post_status doesn't
 * pre-empt the explicit save. Registration assertions target the hook's own
 * callback (not the global action name) to stay isolated from other tests.
 */
class CacheInvalidationTest extends BaseTestCase
{
    public function test_inert_when_no_drivers(): void
    {
        $hook = $this->makeHook([], ['post']);
        $hook->register();

        $this->assertFalse(has_action('save_post', [$hook, 'onSave']), 'No drivers must bind no actions.');
        $this->assertFalse(has_action('transition_post_status', [$hook, 'onTransition']));
    }

    public function test_binds_actions_when_drivers_present(): void
    {
        $hook = $this->makeHook([$this->spyDriver()], ['post']);
        $hook->register();

        $this->assertNotFalse(has_action('save_post', [$hook, 'onSave']));
        $this->assertNotFalse(has_action('acf/save_post', [$hook, 'onSave']));
        $this->assertNotFalse(has_action('before_delete_post', [$hook, 'onDelete']));
    }

    public function test_dispatches_on_save_for_tracked_type(): void
    {
        $postId = self::insertPost('post');

        $spy  = $this->spyDriver();
        $hook = $this->makeHook([$spy], ['post']);
        $hook->register();

        do_action('save_post', $postId);

        $this->assertCount(1, $spy->contexts);
        $this->assertSame('save', $spy->contexts[0]->event);
        $this->assertSame('post', $spy->contexts[0]->postType);
        $this->assertSame($postId, $spy->contexts[0]->postId);
    }

    public function test_skips_untracked_post_type(): void
    {
        $postId = self::insertPost('post');

        $spy  = $this->spyDriver();
        $hook = $this->makeHook([$spy], ['card']); // only cares about 'card'
        $hook->register();

        do_action('save_post', $postId);

        $this->assertCount(0, $spy->contexts);
    }

    public function test_skips_ignored_post_type(): void
    {
        $postId = self::insertPost('revision');

        $spy  = $this->spyDriver();
        $hook = $this->makeHook([$spy], []); // all types
        $hook->register();

        do_action('save_post', $postId);

        $this->assertCount(0, $spy->contexts);
    }

    public function test_coalesces_save_and_acf_save(): void
    {
        $postId = self::insertPost('post');

        $spy  = $this->spyDriver();
        $hook = $this->makeHook([$spy], ['post']);
        $hook->register();

        do_action('save_post', $postId);        // editor save…
        do_action('acf/save_post', $postId);    // …fires ACF too, same request

        $this->assertCount(1, $spy->contexts, 'save_post + acf/save_post must coalesce to one dispatch.');
    }

    public function test_driver_failure_is_isolated(): void
    {
        $postId = self::insertPost('post');

        $boom = new class implements CacheDriver {
            public function invalidate(CacheContext $context): void
            {
                throw new \RuntimeException('boom');
            }
        };
        $spy  = $this->spyDriver();
        $hook = $this->makeHook([$boom, $spy], ['post']);
        $hook->register();

        do_action('save_post', $postId);

        // The throwing driver did not prevent the sibling from running, and no
        // exception bubbled out of the save.
        $this->assertCount(1, $spy->contexts);
    }

    // --- helpers -----------------------------------------------------------

    /**
     * @param CacheDriver[] $drivers
     * @param string[]      $types
     */
    private function makeHook(array $drivers, array $types): CacheInvalidation
    {
        return new class($drivers, $types) extends CacheInvalidation {
            /**
             * @param CacheDriver[] $injectedDrivers
             * @param string[]      $injectedTypes
             */
            public function __construct(
                private array $injectedDrivers,
                private array $injectedTypes,
            ) {}

            protected function drivers(): array
            {
                return $this->injectedDrivers;
            }

            protected function postTypes(): array
            {
                return $this->injectedTypes;
            }
        };
    }

    /**
     * A CacheDriver that records every context it receives.
     */
    private function spyDriver(): CacheDriver
    {
        return new class implements CacheDriver {
            /** @var CacheContext[] */
            public array $contexts = [];

            public function invalidate(CacheContext $context): void
            {
                $this->contexts[] = $context;
            }
        };
    }

    private static function insertPost(string $type): int
    {
        return (int) wp_insert_post([
            'post_type'   => $type,
            'post_status' => 'publish',
            'post_title'  => 'Test ' . $type,
        ]);
    }
}
