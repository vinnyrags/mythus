<?php

namespace Mythus\Tests\Unit\Support\Rest;

use Mythus\Contracts\Routable;
use Mythus\Support\Rest\Endpoint;
use WorDBless\BaseTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class EndpointTest extends BaseTestCase
{
    /**
     * Create a concrete stub endpoint for testing.
     */
    private function createEndpoint(): Endpoint
    {
        return new class extends Endpoint {
            public function getRoute(): string
            {
                return '/health';
            }

            public function getMethods(): string|array
            {
                return 'GET';
            }

            public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
            {
                return ['status' => 'ok'];
            }

            public function getPermission(WP_REST_Request $request): bool|WP_Error
            {
                return true;
            }
        };
    }

    /**
     * Test that Endpoint implements Routable.
     */
    public function testImplementsRoutable(): void
    {
        $endpoint = $this->createEndpoint();
        $this->assertInstanceOf(Routable::class, $endpoint);
    }

    /**
     * Test that getArgs returns an empty array by default.
     */
    public function testGetArgsReturnsEmptyArrayByDefault(): void
    {
        $endpoint = $this->createEndpoint();
        $this->assertSame([], $endpoint->getArgs());
    }

    /**
     * Test that toRouteArgs returns the correct structure.
     */
    public function testToRouteArgsReturnsCorrectStructure(): void
    {
        $endpoint = $this->createEndpoint();
        $args = $endpoint->toRouteArgs();

        $this->assertArrayHasKey('methods', $args);
        $this->assertArrayHasKey('callback', $args);
        $this->assertArrayHasKey('permission_callback', $args);
        $this->assertArrayHasKey('args', $args);
    }

    /**
     * Test that toRouteArgs methods matches getMethod return value.
     */
    public function testToRouteArgsContainsMethods(): void
    {
        $endpoint = $this->createEndpoint();
        $args = $endpoint->toRouteArgs();

        $this->assertSame('GET', $args['methods']);
    }

    /**
     * Test that toRouteArgs callback is a callable array referencing the endpoint.
     */
    public function testToRouteArgsCallbackReferencesEndpoint(): void
    {
        $endpoint = $this->createEndpoint();
        $args = $endpoint->toRouteArgs();

        $this->assertIsArray($args['callback']);
        $this->assertSame($endpoint, $args['callback'][0]);
        $this->assertSame('callback', $args['callback'][1]);
    }

    /**
     * Test that toRouteArgs permission_callback is a callable array referencing the endpoint.
     */
    public function testToRouteArgsPermissionCallbackReferencesEndpoint(): void
    {
        $endpoint = $this->createEndpoint();
        $args = $endpoint->toRouteArgs();

        $this->assertIsArray($args['permission_callback']);
        $this->assertSame($endpoint, $args['permission_callback'][0]);
        $this->assertSame('getPermission', $args['permission_callback'][1]);
    }

    /**
     * Test that toRouteArgs args matches getArgs return value.
     */
    public function testToRouteArgsContainsArgs(): void
    {
        $endpoint = $this->createEndpoint();
        $args = $endpoint->toRouteArgs();

        $this->assertSame([], $args['args']);
    }

    /**
     * Test that a subclass can override getArgs.
     */
    public function testGetArgsCanBeOverridden(): void
    {
        $endpoint = new class extends Endpoint {
            public function getRoute(): string
            {
                return '/items';
            }

            public function getMethods(): string|array
            {
                return 'GET';
            }

            public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
            {
                return [];
            }

            public function getPermission(WP_REST_Request $request): bool|WP_Error
            {
                return true;
            }

            public function getArgs(): array
            {
                return [
                    'page' => [
                        'required' => false,
                        'default'  => 1,
                    ],
                ];
            }
        };

        $args = $endpoint->toRouteArgs();
        $this->assertArrayHasKey('page', $args['args']);
        $this->assertSame(1, $args['args']['page']['default']);
    }

    /**
     * Test that toRouteArgs works with array methods.
     */
    public function testToRouteArgsWithArrayMethods(): void
    {
        $endpoint = new class extends Endpoint {
            public function getRoute(): string
            {
                return '/items';
            }

            public function getMethods(): string|array
            {
                return ['GET', 'POST'];
            }

            public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
            {
                return [];
            }

            public function getPermission(WP_REST_Request $request): bool|WP_Error
            {
                return true;
            }
        };

        $args = $endpoint->toRouteArgs();
        $this->assertSame(['GET', 'POST'], $args['methods']);
    }
}
