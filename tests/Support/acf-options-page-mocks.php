<?php

/**
 * Mock ACF options page functions for testing.
 *
 * These global functions delegate to AcfOptionsPageRecorder so tests
 * can assert which options pages were registered and with what config.
 */

use Mythus\Tests\Support\AcfOptionsPageRecorder;

if (!function_exists('acf_add_options_page')) {
    function acf_add_options_page(array $config): void
    {
        AcfOptionsPageRecorder::addPage($config);
    }
}

if (!function_exists('acf_add_options_sub_page')) {
    function acf_add_options_sub_page(array $config): void
    {
        AcfOptionsPageRecorder::addSubPage($config);
    }
}
