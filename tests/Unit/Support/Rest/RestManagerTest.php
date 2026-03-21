<?php

namespace Mythus\Tests\Unit\Support\Rest;

use DI\Container;
use Mythus\Support\Rest\Endpoint;
use Mythus\Support\Rest\RestManager;
use Mythus\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Concrete stub endpoints for registerAll tests.
 */
class StubEndpointA extends Endpoint
{
    public static bool $registered = false;

    public function getRoute(): string
    {
        return '/alpha';
    }

    public function getMethods(): string|array
    {
        return 'GET';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'alpha'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

class StubEndpointB extends Endpoint
{
    public static bool $registered = false;

    public function getRoute(): string
    {
        return '/beta';
    }

    public function getMethods(): string|array
    {
        return 'POST';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'beta'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

class StubEndpointDisabled extends Endpoint
{
    public function getRoute(): string
    {
        return '/disabled';
    }

    public function getMethods(): string|array
    {
        return 'GET';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'disabled'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

/**
 * Unit tests for the RestManager class.
 */
class RestManagerTest extends BaseTestCase
{
    use HasContainer;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->buildTestContainer();
    }

    /**
     * Test normalize converts indexed entries to [class => true].
     */
    public function testNormalizeIndexedEntries(): void
    {
        $result = RestManager::normalize([
            'App\Endpoints\EndpointA',
            'App\Endpoints\EndpointB',
        ]);

        $this->assertSame([
            'App\Endpoints\EndpointA' => true,
            'App\Endpoints\EndpointB' => true,
        ], $result);
    }

    /**
     * Test normalize preserves associative false entries.
     */
    public function testNormalizeAssociativeFalseEntries(): void
    {
        $result = RestManager::normalize([
            'App\Endpoints\EndpointA' => false,
        ]);

        $this->assertSame([
            'App\Endpoints\EndpointA' => false,
        ], $result);
    }

    /**
     * Test normalize handles mixed arrays.
     */
    public function testNormalizeMixedArray(): void
    {
        $result = RestManager::normalize([
            'App\Endpoints\EndpointA',
            'App\Endpoints\EndpointB' => false,
            'App\Endpoints\EndpointC',
        ]);

        $this->assertSame([
            'App\Endpoints\EndpointA' => true,
            'App\Endpoints\EndpointB' => false,
            'App\Endpoints\EndpointC' => true,
        ], $result);
    }

    /**
     * Test normalize with empty array.
     */
    public function testNormalizeEmptyArray(): void
    {
        $this->assertSame([], RestManager::normalize([]));
    }

    /**
     * Test isEnabled returns true for enabled routes.
     */
    public function testIsEnabledReturnsTrueForEnabled(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => true,
        ], $this->container, 'test/v1');

        $this->assertTrue($manager->isEnabled('App\Endpoints\EndpointA'));
    }

    /**
     * Test isEnabled returns false for disabled routes.
     */
    public function testIsEnabledReturnsFalseForDisabled(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => false,
        ], $this->container, 'test/v1');

        $this->assertFalse($manager->isEnabled('App\Endpoints\EndpointA'));
    }

    /**
     * Test isEnabled returns false for unknown routes.
     */
    public function testIsEnabledReturnsFalseForUnknown(): void
    {
        $manager = new RestManager([], $this->container, 'test/v1');

        $this->assertFalse($manager->isEnabled('App\Endpoints\Unknown'));
    }

    /**
     * Test getEnabled returns all routes when none are disabled.
     */
    public function testGetEnabledReturnsAllWhenNoneDisabled(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => true,
            'App\Endpoints\EndpointB' => true,
        ], $this->container, 'test/v1');

        $this->assertSame([
            'App\Endpoints\EndpointA',
            'App\Endpoints\EndpointB',
        ], $manager->getEnabled());
    }

    /**
     * Test getEnabled excludes routes set to false.
     */
    public function testGetEnabledExcludesDisabled(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => true,
            'App\Endpoints\EndpointB' => false,
            'App\Endpoints\EndpointC' => true,
        ], $this->container, 'test/v1');

        $this->assertSame([
            'App\Endpoints\EndpointA',
            'App\Endpoints\EndpointC',
        ], array_values($manager->getEnabled()));
    }

    /**
     * Test getDisabled returns only the false entries.
     */
    public function testGetDisabledReturnsOnlyFalseEntries(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => true,
            'App\Endpoints\EndpointB' => false,
            'App\Endpoints\EndpointC' => false,
        ], $this->container, 'test/v1');

        $this->assertSame([
            'App\Endpoints\EndpointB',
            'App\Endpoints\EndpointC',
        ], array_values($manager->getDisabled()));
    }

    /**
     * Test getDisabled returns empty array when all enabled.
     */
    public function testGetDisabledReturnsEmptyWhenAllEnabled(): void
    {
        $manager = new RestManager([
            'App\Endpoints\EndpointA' => true,
        ], $this->container, 'test/v1');

        $this->assertSame([], $manager->getDisabled());
    }

    /**
     * Test getNamespace returns the configured namespace.
     */
    public function testGetNamespaceReturnsConfiguredNamespace(): void
    {
        $manager = new RestManager([], $this->container, 'theme/v1');

        $this->assertSame('theme/v1', $manager->getNamespace());
    }

    /**
     * Test registerAll calls register_rest_route for enabled endpoints only.
     */
    public function testRegisterAllRegistersEnabledOnly(): void
    {
        $manager = new RestManager([
            StubEndpointA::class => true,
            StubEndpointB::class => true,
            StubEndpointDisabled::class => false,
        ], $this->container, 'test/v1');

        $manager->registerAll();

        $routes = rest_get_server()->get_routes();

        $this->assertArrayHasKey('/test/v1/alpha', $routes);
        $this->assertArrayHasKey('/test/v1/beta', $routes);
        $this->assertArrayNotHasKey('/test/v1/disabled', $routes);
    }

    /**
     * Test registerAll with empty routes does nothing.
     */
    public function testRegisterAllWithEmptyRoutes(): void
    {
        $manager = new RestManager([], $this->container, 'test/v1');
        $manager->registerAll();

        $this->assertTrue(true);
    }
}
