<?php

declare(strict_types=1);

namespace Mythus\Support\Cache;

/**
 * Immutable description of a content change, handed to each CacheDriver.
 *
 * A newable value object (constructed at the point of dispatch), not a service.
 * Deliberately minimal — carries only what a driver needs to decide what to
 * invalidate. Add fields (e.g. status transitions) when a driver actually uses them.
 */
final class CacheContext
{
    /**
     * @param string $event    'save' | 'status_change' | 'delete' | 'trash'
     * @param int    $postId   The post the change concerns.
     * @param string $postType The post's type.
     */
    public function __construct(
        public readonly string $event,
        public readonly int $postId,
        public readonly string $postType,
    ) {}
}
