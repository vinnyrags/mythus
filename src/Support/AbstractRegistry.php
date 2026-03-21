<?php

declare(strict_types=1);

namespace Mythus\Support;

use DI\Container;

/**
 * Base class for registries that manage toggleable class lists.
 *
 * Accepts a normalized map of [class-string => bool] and provides
 * filtering, normalization, and container-based resolution.
 */
abstract class AbstractRegistry
{
    public function __construct(
        private readonly array $items,
        protected readonly Container $container,
    ) {}

    /**
     * Normalize a mixed array into [class-string => bool].
     *
     * Accepts both indexed arrays (implying enabled) and associative
     * arrays with explicit bool values.
     *
     * @param array<int|class-string, class-string|bool> $items
     * @return array<class-string, bool>
     */
    public static function normalize(array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = true;
            } else {
                $normalized[$key] = (bool) $value;
            }
        }

        return $normalized;
    }

    /**
     * Check if a specific item is enabled.
     */
    public function isEnabled(string $item): bool
    {
        return $this->items[$item] ?? false;
    }

    /**
     * Get all enabled item class names.
     *
     * @return array<class-string>
     */
    public function getEnabled(): array
    {
        return array_keys(array_filter($this->items));
    }

    /**
     * Get all disabled item class names.
     *
     * @return array<class-string>
     */
    public function getDisabled(): array
    {
        return array_keys(array_filter($this->items, fn(bool $enabled) => !$enabled));
    }

    /**
     * Resolve and register all enabled items.
     */
    abstract public function registerAll(): void;
}
