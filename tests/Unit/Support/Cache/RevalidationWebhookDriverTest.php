<?php

declare(strict_types=1);

namespace Mythus\Tests\Unit\Support\Cache;

use Mythus\Support\Cache\CacheContext;
use Mythus\Support\Cache\RevalidationWebhookDriver;
use WorDBless\BaseTestCase;

/**
 * Unit tests for the RevalidationWebhookDriver.
 *
 * Uses the 'pre_http_request' filter as the seam to capture (and short-circuit)
 * the outbound wp_remote_post without hitting the network.
 */
class RevalidationWebhookDriverTest extends BaseTestCase
{
    /** @var array<int, array{url: string, args: array<string, mixed>}> */
    private array $captured = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->captured = [];
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->captured[] = ['url' => $url, 'args' => $args];

            return ['response' => ['code' => 200], 'body' => ''];
        }, 10, 3);
    }

    public function test_inert_when_secret_empty(): void
    {
        $driver = new RevalidationWebhookDriver(
            endpoint: 'https://example.test/api/revalidate',
            secret: '',
            postTypePathMap: ['card' => ['/', '/cards']],
        );

        $driver->invalidate($this->context('card'));

        $this->assertCount(0, $this->captured, 'Empty secret must send no request.');
    }

    public function test_posts_expected_body_for_tracked_type(): void
    {
        $driver = new RevalidationWebhookDriver(
            endpoint: 'https://example.test/api/revalidate',
            secret: 'sekret',
            postTypePathMap: [
                'card'    => ['/', '/cards', '/collection', '/livestream-shop'],
                'product' => ['/', '/livestream-shop'],
            ],
        );

        $driver->invalidate($this->context('card'));

        $this->assertCount(1, $this->captured);
        $this->assertSame('https://example.test/api/revalidate', $this->captured[0]['url']);
        $this->assertSame('POST', $this->captured[0]['args']['method']);

        $body = json_decode((string) $this->captured[0]['args']['body'], true);
        $this->assertSame('sekret', $body['secret']);
        $this->assertSame(['/', '/cards', '/collection', '/livestream-shop'], $body['paths']);
    }

    public function test_skips_untracked_post_type(): void
    {
        $driver = new RevalidationWebhookDriver(
            endpoint: 'https://example.test/api/revalidate',
            secret: 'sekret',
            postTypePathMap: ['card' => ['/cards']],
        );

        $driver->invalidate($this->context('product')); // not in the map

        $this->assertCount(0, $this->captured);
    }

    private function context(string $postType): CacheContext
    {
        return new CacheContext('save', 123, $postType);
    }
}
