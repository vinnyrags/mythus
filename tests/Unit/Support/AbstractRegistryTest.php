<?php

namespace Mythus\Tests\Unit\Support;

use DI\Container;
use Mythus\Support\AbstractRegistry;
use Mythus\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;

/**
 * Unit tests for the AbstractRegistry base class.
 */
class AbstractRegistryTest extends BaseTestCase
{
    use HasContainer;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->buildTestContainer();
    }

    /**
     * Create a concrete anonymous subclass for testing.
     *
     * @param array<class-string, bool> $items
     */
    private function createRegistry(array $items): AbstractRegistry
    {
        return new class($items, $this->container) extends AbstractRegistry {
            public function registerAll(): void
            {
                // no-op for testing base class methods
            }
        };
    }

    /**
     * Test normalize converts indexed entries to [class => true].
     */
    public function testNormalizeIndexedEntries(): void
    {
        $result = AbstractRegistry::normalize([
            'App\ItemA',
            'App\ItemB',
        ]);

        $this->assertSame([
            'App\ItemA' => true,
            'App\ItemB' => true,
        ], $result);
    }

    /**
     * Test normalize preserves associative false entries.
     */
    public function testNormalizeAssociativeFalseEntries(): void
    {
        $result = AbstractRegistry::normalize([
            'App\ItemA' => false,
        ]);

        $this->assertSame([
            'App\ItemA' => false,
        ], $result);
    }

    /**
     * Test normalize handles mixed arrays.
     */
    public function testNormalizeMixedArray(): void
    {
        $result = AbstractRegistry::normalize([
            'App\ItemA',
            'App\ItemB' => false,
            'App\ItemC',
        ]);

        $this->assertSame([
            'App\ItemA' => true,
            'App\ItemB' => false,
            'App\ItemC' => true,
        ], $result);
    }

    /**
     * Test normalize with empty array.
     */
    public function testNormalizeEmptyArray(): void
    {
        $this->assertSame([], AbstractRegistry::normalize([]));
    }

    /**
     * Test isEnabled returns true for enabled items.
     */
    public function testIsEnabledReturnsTrueForEnabled(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => true,
        ]);

        $this->assertTrue($registry->isEnabled('App\ItemA'));
    }

    /**
     * Test isEnabled returns false for disabled items.
     */
    public function testIsEnabledReturnsFalseForDisabled(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => false,
        ]);

        $this->assertFalse($registry->isEnabled('App\ItemA'));
    }

    /**
     * Test isEnabled returns false for unknown items.
     */
    public function testIsEnabledReturnsFalseForUnknown(): void
    {
        $registry = $this->createRegistry([]);

        $this->assertFalse($registry->isEnabled('App\Unknown'));
    }

    /**
     * Test getEnabled returns all items when none are disabled.
     */
    public function testGetEnabledReturnsAllWhenNoneDisabled(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => true,
            'App\ItemB' => true,
        ]);

        $this->assertSame([
            'App\ItemA',
            'App\ItemB',
        ], $registry->getEnabled());
    }

    /**
     * Test getEnabled excludes items set to false.
     */
    public function testGetEnabledExcludesDisabled(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => true,
            'App\ItemB' => false,
            'App\ItemC' => true,
        ]);

        $this->assertSame([
            'App\ItemA',
            'App\ItemC',
        ], array_values($registry->getEnabled()));
    }

    /**
     * Test getDisabled returns only the false entries.
     */
    public function testGetDisabledReturnsOnlyFalseEntries(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => true,
            'App\ItemB' => false,
            'App\ItemC' => false,
        ]);

        $this->assertSame([
            'App\ItemB',
            'App\ItemC',
        ], array_values($registry->getDisabled()));
    }

    /**
     * Test getDisabled returns empty array when all enabled.
     */
    public function testGetDisabledReturnsEmptyWhenAllEnabled(): void
    {
        $registry = $this->createRegistry([
            'App\ItemA' => true,
        ]);

        $this->assertSame([], $registry->getDisabled());
    }

    /**
     * Test getEnabled returns empty array when constructed with no items.
     */
    public function testGetEnabledReturnsEmptyForNoItems(): void
    {
        $registry = $this->createRegistry([]);

        $this->assertSame([], $registry->getEnabled());
    }

    /**
     * Test getDisabled returns empty array when constructed with no items.
     */
    public function testGetDisabledReturnsEmptyForNoItems(): void
    {
        $registry = $this->createRegistry([]);

        $this->assertSame([], $registry->getDisabled());
    }
}
