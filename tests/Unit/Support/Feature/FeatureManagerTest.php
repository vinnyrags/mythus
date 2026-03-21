<?php

namespace Mythus\Tests\Unit\Support\Feature;

use DI\Container;
use Mythus\Contracts\Feature;
use Mythus\Contracts\Registrable;
use Mythus\Support\Feature\FeatureManager;
use Mythus\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;

/**
 * Concrete test feature for registerAll tests.
 */
class StubFeatureA implements Feature
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

class StubFeatureB implements Feature
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

class StubFeatureDisabled implements Feature
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

class StubFeatureThrowing implements Feature
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
        throw new \RuntimeException('Feature registration failed intentionally');
    }
}

class StubFeatureAfterThrowing implements Feature
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

/**
 * Stub that implements Registrable but NOT Feature — should be skipped by FeatureManager.
 */
class StubRegistrableOnly implements Registrable
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

/**
 * Unit tests for the FeatureManager class.
 */
class FeatureManagerTest extends BaseTestCase
{
    use HasContainer;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->buildTestContainer();
        StubFeatureA::$registered = false;
        StubFeatureB::$registered = false;
        StubFeatureDisabled::$registered = false;
        StubFeatureThrowing::$registered = false;
        StubFeatureAfterThrowing::$registered = false;
        StubRegistrableOnly::$registered = false;
    }

    /**
     * Test normalize converts indexed entries to [class => true].
     */
    public function testNormalizeIndexedEntries(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA',
            'App\Features\FeatureB',
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => true,
        ], $result);
    }

    /**
     * Test normalize preserves associative false entries.
     */
    public function testNormalizeAssociativeFalseEntries(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA' => false,
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => false,
        ], $result);
    }

    /**
     * Test normalize handles mixed arrays.
     */
    public function testNormalizeMixedArray(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA',
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC',
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => true,
        ], $result);
    }

    /**
     * Test normalize with empty array.
     */
    public function testNormalizeEmptyArray(): void
    {
        $this->assertSame([], FeatureManager::normalize([]));
    }

    /**
     * Test getEnabled returns all features when none are disabled.
     */
    public function testGetEnabledReturnsAllWhenNoneDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => true,
        ], $this->container);

        $this->assertSame([
            'App\Features\FeatureA',
            'App\Features\FeatureB',
        ], $manager->getEnabled());
    }

    /**
     * Test getEnabled excludes features set to false.
     */
    public function testGetEnabledExcludesDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => true,
        ], $this->container);

        $this->assertSame([
            'App\Features\FeatureA',
            'App\Features\FeatureC',
        ], array_values($manager->getEnabled()));
    }

    /**
     * Test isEnabled returns true for enabled features.
     */
    public function testIsEnabledReturnsTrueForEnabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
        ], $this->container);

        $this->assertTrue($manager->isEnabled('App\Features\FeatureA'));
    }

    /**
     * Test isEnabled returns false for disabled features.
     */
    public function testIsEnabledReturnsFalseForDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => false,
        ], $this->container);

        $this->assertFalse($manager->isEnabled('App\Features\FeatureA'));
    }

    /**
     * Test isEnabled returns false for unknown features.
     */
    public function testIsEnabledReturnsFalseForUnknown(): void
    {
        $manager = new FeatureManager([], $this->container);

        $this->assertFalse($manager->isEnabled('App\Features\Unknown'));
    }

    /**
     * Test getDisabled returns only the false entries.
     */
    public function testGetDisabledReturnsOnlyFalseEntries(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => false,
        ], $this->container);

        $this->assertSame([
            'App\Features\FeatureB',
            'App\Features\FeatureC',
        ], array_values($manager->getDisabled()));
    }

    /**
     * Test getDisabled returns empty array when all enabled.
     */
    public function testGetDisabledReturnsEmptyWhenAllEnabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
        ], $this->container);

        $this->assertSame([], $manager->getDisabled());
    }

    /**
     * Test registerAll instantiates and registers enabled features only.
     */
    public function testRegisterAllRegistersEnabledOnly(): void
    {
        $manager = new FeatureManager([
            StubFeatureA::class => true,
            StubFeatureB::class => true,
            StubFeatureDisabled::class => false,
        ], $this->container);

        $manager->registerAll();

        $this->assertTrue(StubFeatureA::$registered);
        $this->assertTrue(StubFeatureB::$registered);
        $this->assertFalse(StubFeatureDisabled::$registered);
    }

    /**
     * Test registerAll with empty features does nothing.
     */
    public function testRegisterAllWithEmptyFeatures(): void
    {
        $manager = new FeatureManager([], $this->container);
        $manager->registerAll();

        $this->assertTrue(true);
    }

    /**
     * Test registerAll continues after one feature throws an exception.
     */
    public function testRegisterAllContinuesAfterFeatureException(): void
    {
        $manager = new FeatureManager([
            StubFeatureA::class => true,
            StubFeatureThrowing::class => true,
            StubFeatureAfterThrowing::class => true,
        ], $this->container);

        // Should not throw - exceptions are caught internally
        $manager->registerAll();

        // First feature registered before the throwing one
        $this->assertTrue(StubFeatureA::$registered);

        // Throwing feature was called (it sets $registered before throwing)
        $this->assertTrue(StubFeatureThrowing::$registered);

        // Feature after the throwing one should still register
        $this->assertTrue(StubFeatureAfterThrowing::$registered);
    }

    /**
     * Test registerAll handles container resolution failures gracefully.
     */
    public function testRegisterAllHandlesContainerResolutionFailure(): void
    {
        $manager = new FeatureManager([
            StubFeatureA::class => true,
            'NonExistent\\Feature\\Class' => true,
            StubFeatureB::class => true,
        ], $this->container);

        // Should not throw - container errors are caught
        $manager->registerAll();

        // Features that could be resolved should still register
        $this->assertTrue(StubFeatureA::$registered);
        $this->assertTrue(StubFeatureB::$registered);
    }

    /**
     * Test registerAll isolates errors to individual features.
     */
    public function testRegisterAllIsolatesErrors(): void
    {
        $manager = new FeatureManager([
            StubFeatureThrowing::class => true,
            StubFeatureB::class => true,
        ], $this->container);

        $manager->registerAll();

        // Despite first feature throwing, second should register
        $this->assertTrue(StubFeatureB::$registered);
    }

    /**
     * Test registerAll skips classes that implement Registrable but not Feature.
     */
    public function testRegisterAllSkipsNonFeatureRegistrable(): void
    {
        $manager = new FeatureManager([
            StubRegistrableOnly::class => true,
            StubFeatureA::class => true,
        ], $this->container);

        $manager->registerAll();

        // Registrable-only class should be skipped
        $this->assertFalse(StubRegistrableOnly::$registered);

        // Feature class should still register
        $this->assertTrue(StubFeatureA::$registered);
    }
}
