<?php

declare(strict_types=1);

namespace Mythus\Support\Cache;

/**
 * Immutable description of a content change, handed to each CacheDriver.
 *
 * A newable value object (constructed at the point of dispatch), not a service.
 */
final class CacheContext
{
    /**
     * @param string               $event     'save' | 'status_change' | 'delete' | 'trash' | 'sync'
     * @param int                  $postId    The post the change concerns (0 when not post-scoped).
     * @param string               $postType  The post's type (empty when unknown).
     * @param string|null          $oldStatus Previous status, for status_change events.
     * @param string|null          $newStatus New status, for status_change events.
     * @param array<string, mixed> $meta      Free-form extras (e.g. ['reason' => 'stock']).
     */
    public function __construct(
        public readonly string $event,
        public readonly int $postId,
        public readonly string $postType,
        public readonly ?string $oldStatus = null,
        public readonly ?string $newStatus = null,
        public readonly array $meta = [],
    ) {}
}
