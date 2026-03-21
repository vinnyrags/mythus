<?php

declare(strict_types=1);

namespace Mythus\Hooks;

use Mythus\Contracts\Hook;

/**
 * Abstract base class for block style registration hooks.
 *
 * Provides a declarative pattern for registering custom WordPress block styles.
 * Child classes implement styles() to return a map of block names to style arrays.
 *
 * Example:
 *   protected function styles(): array
 *   {
 *       return [
 *           'core/group' => [
 *               ['name' => 'dark', 'label' => __('Dark', 'my-theme')],
 *           ],
 *       ];
 *   }
 */
abstract class BlockStyles implements Hook
{
    /**
     * Define the block styles to register.
     *
     * @return array<string, array<int, array{name: string, label: string}>>
     */
    abstract protected function styles(): array;

    /**
     * Register the init hook for block style registration.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register all declared block styles.
     */
    public function registerStyles(): void
    {
        foreach ($this->styles() as $block => $styles) {
            foreach ($styles as $style) {
                register_block_style($block, $style);
            }
        }
    }
}
