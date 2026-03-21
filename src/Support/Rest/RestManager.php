<?php

declare(strict_types=1);

namespace Mythus\Support\Rest;

use DI\Container;
use Mythus\Support\AbstractRegistry;
use Throwable;

/**
 * Manages REST API endpoint registration for providers.
 *
 * Accepts a normalized route map of [class-string => bool] and handles
 * filtering and registration. Routes set to false are excluded.
 */
class RestManager extends AbstractRegistry
{
    public function __construct(
        /** @var array<class-string<Endpoint>, bool> */
        array $routes,
        Container $container,
        private readonly string $namespace,
    ) {
        parent::__construct($routes, $container);
    }

    /**
     * Get the REST namespace (e.g. 'theme/v1').
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Resolve and register all enabled endpoints via register_rest_route().
     *
     * Catches exceptions per-endpoint so a single broken endpoint doesn't
     * prevent subsequent endpoints from registering.
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $routeClass) {
            try {
                /** @var Endpoint $endpoint */
                $endpoint = $this->container->get($routeClass);
                register_rest_route($this->namespace, $endpoint->getRoute(), $endpoint->toRouteArgs());
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'RestManager: Failed to register endpoint %s: %s',
                    $routeClass,
                    $e->getMessage()
                ));
            }
        }
    }
}
