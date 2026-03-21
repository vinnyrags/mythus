<?php

namespace Mythus\Tests\Support;

/**
 * Records calls to ACF options page functions for test assertions.
 *
 * Define mock acf_add_options_page() and acf_add_options_sub_page()
 * functions in the global namespace that delegate to this recorder.
 */
class AcfOptionsPageRecorder
{
    /** @var array<int, array<string, mixed>> */
    public static array $pages = [];

    /** @var array<int, array<string, mixed>> */
    public static array $subPages = [];

    public static function reset(): void
    {
        self::$pages = [];
        self::$subPages = [];
    }

    public static function addPage(array $config): void
    {
        self::$pages[] = $config;
    }

    public static function addSubPage(array $config): void
    {
        self::$subPages[] = $config;
    }
}
