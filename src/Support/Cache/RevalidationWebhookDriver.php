<?php

declare(strict_types=1);

namespace Mythus\Support\Cache;

use Mythus\Contracts\CacheDriver;

/**
 * Fires a non-blocking POST to a headless frontend's on-demand revalidation
 * endpoint (e.g. a Next.js /api/revalidate) when tracked content changes.
 *
 * Wire contract: POST { "secret": <shared secret>, "paths": string[] }.
 *
 * Inert when the secret is empty — safe to register before the secret is
 * deployed, and on environments (local) that can't reach the frontend. The
 * request is fire-and-forget (blocking: false), so a slow or down frontend can
 * never delay or fail the originating save.
 */
final class RevalidationWebhookDriver implements CacheDriver
{
    /**
     * @param string                 $endpoint        Full URL of the revalidation endpoint.
     * @param string                 $secret          Shared secret; '' disables the driver.
     * @param array<string, string[]> $postTypePathMap post_type => Next paths to revalidate.
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly string $secret,
        private readonly array $postTypePathMap,
    ) {}

    public function invalidate(CacheContext $context): void
    {
        if ($this->secret === '' || $this->endpoint === '') {
            return;
        }

        $paths = $this->postTypePathMap[$context->postType] ?? null;
        if ($paths === null || $paths === []) {
            return;
        }

        wp_remote_post($this->endpoint, [
            'timeout'  => 2,
            'blocking' => false,
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => wp_json_encode([
                'secret' => $this->secret,
                'paths'  => array_values($paths),
            ]),
        ]);
    }
}
