<?php

declare(strict_types=1);

namespace Mythus\Contracts;

/**
 * Contract for REST API endpoints.
 */
interface Routable
{
    /**
     * The route pattern (e.g. '/health', '/projects/(?P<id>\d+)').
     */
    public function getRoute(): string;

    /**
     * Assemble the args array for register_rest_route().
     */
    public function toRouteArgs(): array;
}
