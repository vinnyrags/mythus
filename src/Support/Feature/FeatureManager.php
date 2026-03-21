<?php

declare(strict_types=1);

namespace Mythus\Support\Feature;

use Mythus\Contracts\Feature;
use Mythus\Support\AbstractRegistry;

/**
 * Manages toggleable feature registration for providers.
 *
 * Resolves feature classes from the DI container and validates
 * that they implement the Feature interface before registering.
 */
class FeatureManager extends AbstractRegistry
{
    /**
     * Resolve and register all enabled features.
     *
     * Validates that each class implements Feature. Non-Feature classes
     * are skipped with a warning log entry.
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $featureClass) {
            try {
                $feature = $this->container->get($featureClass);

                if (!$feature instanceof Feature) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'FeatureManager: %s does not implement Feature. '
                        . 'Move it to $hooks for always-active behavior, '
                        . 'or implement Feature for toggleable features.',
                        $featureClass
                    ));
                    continue;
                }

                $feature->register();
            } catch (\Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'FeatureManager: Failed to register feature %s: %s',
                    $featureClass,
                    $e->getMessage()
                ));
            }
        }
    }
}
