<?php

declare(strict_types=1);

namespace Mythus\Contracts;

use Mythus\Support\Cache\CacheContext;

/**
 * Contract for a downstream cache-invalidation strategy.
 *
 * Drivers are the pluggable "paths to caching" — a site registers zero or more
 * (via a CacheInvalidation subclass) to match its actual cache stack (nginx
 * FastCGI page cache, a headless frontend's ISR, etc.). The platform ships the
 * interface + built-in drivers; nothing runs until a site opts in.
 *
 * Implementations MUST NOT throw: a driver handles its own failures (logging as
 * needed) so it never blocks a save or prevents sibling drivers from running.
 */
interface CacheDriver
{
    public function invalidate(CacheContext $context): void;
}
