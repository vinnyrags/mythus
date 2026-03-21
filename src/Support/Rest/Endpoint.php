<?php

declare(strict_types=1);

namespace Mythus\Support\Rest;

use Mythus\Contracts\Routable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Abstract base class for REST API endpoints.
 *
 * Every REST endpoint extends this class and defines its route, methods,
 * callback, and permission logic. The RestManager resolves endpoints from
 * the DI container and registers them via register_rest_route().
 */
abstract class Endpoint implements Routable
{
    /**
     * The route pattern (e.g. '/health', '/projects/(?P<id>\d+)').
     */
    abstract public function getRoute(): string;

    /**
     * HTTP methods this endpoint responds to.
     *
     * @return string|array WP_REST_Server constants (e.g. 'GET', ['GET', 'POST'])
     */
    abstract public function getMethods(): string|array;

    /**
     * Handle the request and return a response.
     */
    abstract public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array;

    /**
     * Check if the current request has permission to access this endpoint.
     */
    abstract public function getPermission(WP_REST_Request $request): bool|WP_Error;

    /**
     * Argument schema for the endpoint.
     *
     * Override to define expected parameters and validation rules.
     */
    public function getArgs(): array
    {
        return [];
    }

    /**
     * Assemble the args array for register_rest_route().
     *
     * Can be overridden for unusual cases (e.g. multiple method/callback pairs).
     */
    public function toRouteArgs(): array
    {
        return [
            'methods'             => $this->getMethods(),
            'callback'            => [$this, 'callback'],
            'permission_callback' => [$this, 'getPermission'],
            'args'                => $this->getArgs(),
        ];
    }
}
