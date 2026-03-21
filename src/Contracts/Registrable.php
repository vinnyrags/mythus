<?php

declare(strict_types=1);

namespace Mythus\Contracts;

/**
 * Root interface for all registrable classes.
 *
 * Features, hooks, and providers all implement this interface.
 */
interface Registrable
{
    /**
     * Register hooks, filters, and other WordPress integrations.
     */
    public function register(): void;
}
