<?php

declare(strict_types=1);

namespace Mythus\Tests\Unit\Support\Sync;

use Mythus\Support\Sync\SyncEnv;
use PHPUnit\Framework\TestCase;

/**
 * Guards the one-way / downhill pull-safety invariant: syncs only ever flow from a
 * higher environment into a lower one, and production is never a target.
 */
final class SyncEnvTest extends TestCase
{
    public function test_production_is_never_a_sync_target(): void
    {
        $this->assertSame([], SyncEnv::allowedSources('production'));
    }

    public function test_staging_may_only_pull_from_production(): void
    {
        $this->assertSame(['production'], SyncEnv::allowedSources('staging'));
    }

    public function test_local_may_pull_from_staging_and_production(): void
    {
        $this->assertSame(['staging', 'production'], SyncEnv::allowedSources('local'));
    }

    public function test_an_unknown_environment_allows_no_sources(): void
    {
        $this->assertSame([], SyncEnv::allowedSources('nonsense'));
    }
}
