<?php

declare(strict_types=1);

namespace Mythus\Support\Cache;

use FilesystemIterator;
use Mythus\Contracts\CacheDriver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Purges an nginx FastCGI page-cache directory when tracked content changes.
 *
 * For a full-page microcache the pragmatic, safe move is to clear the whole
 * zone on any content change (the same approach the arthouse splash sites use).
 * Guarded to a required path prefix so a misconfiguration can't unlink arbitrary
 * files, and inert when the directory is absent. Sites without a page cache
 * (Redis-only, headless, WP Engine) simply never register this driver.
 */
final class FastcgiPurgeDriver implements CacheDriver
{
    /**
     * @param string $cacheDir       Absolute path to the nginx cache zone directory.
     * @param string $requiredPrefix Safety guard: $cacheDir must live under this.
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $requiredPrefix = '/var/cache/nginx/',
    ) {}

    public function invalidate(CacheContext $context): void
    {
        $dir = rtrim($this->cacheDir, '/');

        if ($dir === '' || strpos($dir . '/', $this->requiredPrefix) !== 0 || !is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
    }
}
