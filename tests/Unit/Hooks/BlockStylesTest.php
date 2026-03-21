<?php

namespace Mythus\Tests\Unit\Hooks;

use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use Mythus\Hooks\BlockStyles;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the BlockStyles abstract class.
 */
class BlockStylesTest extends TestCase
{
    /**
     * Test that BlockStyles implements Hook.
     */
    public function testImplementsHook(): void
    {
        $stub = $this->createBlockStylesStub();
        $this->assertInstanceOf(Hook::class, $stub);
    }

    /**
     * Test that BlockStyles implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $stub = $this->createBlockStylesStub();
        $this->assertInstanceOf(Registrable::class, $stub);
    }

    /**
     * Test that styles method is abstract.
     */
    public function testStylesMethodIsAbstract(): void
    {
        $reflection = new ReflectionClass(BlockStyles::class);
        $method = $reflection->getMethod('styles');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test that registerStyles method is public.
     */
    public function testRegisterStylesMethodIsPublic(): void
    {
        $reflection = new ReflectionClass(BlockStyles::class);
        $method = $reflection->getMethod('registerStyles');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that register method is public.
     */
    public function testRegisterMethodIsPublic(): void
    {
        $reflection = new ReflectionClass(BlockStyles::class);
        $method = $reflection->getMethod('register');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Create a concrete stub of the abstract BlockStyles class.
     */
    private function createBlockStylesStub(): BlockStyles
    {
        return new class extends BlockStyles {
            protected function styles(): array
            {
                return [
                    'core/group' => [
                        ['name' => 'test', 'label' => 'Test'],
                    ],
                ];
            }
        };
    }
}
