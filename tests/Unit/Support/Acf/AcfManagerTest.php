<?php

namespace Mythus\Tests\Unit\Support\Acf;

use Mythus\Support\Acf\AcfManager;
use Mythus\Tests\Support\AcfOptionsPageRecorder;
use WorDBless\BaseTestCase;

// Load mock ACF options page functions.
require_once dirname(__DIR__, 3) . '/Support/acf-options-page-mocks.php';

/**
 * Unit tests for the AcfManager class.
 */
class AcfManagerTest extends BaseTestCase
{
    private string $tempDir;

    public function set_up(): void
    {
        parent::set_up();
        $this->tempDir = sys_get_temp_dir() . '/acf-manager-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        AcfOptionsPageRecorder::reset();
    }

    public function tear_down(): void
    {
        // Clean up temp directory recursively
        $this->removeDirectory($this->tempDir);
        parent::tear_down();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Test hasAcfJson returns false when directory doesn't exist.
     */
    public function testHasAcfJsonReturnsFalseWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $this->assertFalse($manager->hasAcfJson());
    }

    /**
     * Test hasAcfJson returns true when directory exists.
     */
    public function testHasAcfJsonReturnsTrueWithDirectory(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $this->assertTrue($manager->hasAcfJson());
    }

    /**
     * Test getAcfJsonPath returns correct path.
     */
    public function testGetAcfJsonPathReturnsCorrectPath(): void
    {
        $manager = new AcfManager($this->tempDir);

        $this->assertEquals($this->tempDir . '/acf-json', $manager->getAcfJsonPath());
    }

    /**
     * Test initializeHooks does nothing without acf-json directory.
     */
    public function testInitializeHooksSkipsWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $manager->initializeHooks();

        $this->assertFalse(has_filter('acf/settings/load_json', [$manager, 'addLoadPath']));
    }

    /**
     * Test initializeHooks registers load filter with acf-json directory.
     */
    public function testInitializeHooksRegistersLoadFilter(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->initializeHooks();

        $this->assertNotFalse(has_filter('acf/settings/load_json', [$manager, 'addLoadPath']));
    }

    /**
     * Test addLoadPath appends the acf-json path to existing paths.
     */
    public function testAddLoadPathAppendsPath(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $paths = ['/existing/path'];
        $result = $manager->addLoadPath($paths);

        $this->assertCount(2, $result);
        $this->assertEquals('/existing/path', $result[0]);
        $this->assertEquals($this->tempDir . '/acf-json', $result[1]);
    }

    /**
     * Test addLoadPath works with empty paths array.
     */
    public function testAddLoadPathWorksWithEmptyArray(): void
    {
        $manager = new AcfManager($this->tempDir);

        $result = $manager->addLoadPath([]);

        $this->assertCount(1, $result);
        $this->assertEquals($this->tempDir . '/acf-json', $result[0]);
    }

    /**
     * Test registerSavePath does nothing without acf-json directory.
     */
    public function testRegisterSavePathSkipsWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $this->assertFalse(has_filter('acf/settings/save_json'));
    }

    /**
     * Test registerSavePath registers save filter with acf-json directory.
     */
    public function testRegisterSavePathRegistersFilter(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $this->assertNotFalse(has_filter('acf/settings/save_json'));
    }

    /**
     * Test registerSavePath filter returns correct path.
     */
    public function testRegisterSavePathReturnsCorrectPath(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $result = apply_filters('acf/settings/save_json', '/default/path');
        $this->assertEquals($this->tempDir . '/acf-json', $result);
    }

    // ── Options page auto-discovery tests ──────────────────────────────

    /**
     * Test constructor accepts optional text domain parameter.
     */
    public function testConstructorAcceptsTextDomain(): void
    {
        $manager = new AcfManager($this->tempDir);
        $this->assertInstanceOf(AcfManager::class, $manager);

        $manager = new AcfManager($this->tempDir, 'my-plugin');
        $this->assertInstanceOf(AcfManager::class, $manager);
    }

    /**
     * Test initializeHooks registers acf/init action when acf-json exists.
     */
    public function testInitializeHooksRegistersAcfInitAction(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->initializeHooks();

        $this->assertNotFalse(has_action('acf/init', [$manager, 'registerOptionsPages']));
    }

    /**
     * Test registerOptionsPages is a no-op when no options-page JSON files exist.
     */
    public function testRegisterOptionsPagesNoopWithoutFiles(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->registerOptionsPages();

        $this->assertEmpty(AcfOptionsPageRecorder::$pages);
        $this->assertEmpty(AcfOptionsPageRecorder::$subPages);
    }

    /**
     * Test registerOptionsPages ignores field group JSON files.
     */
    public function testRegisterOptionsPagesIgnoresFieldGroupFiles(): void
    {
        $acfDir = $this->tempDir . '/acf-json';
        mkdir($acfDir, 0777, true);
        file_put_contents($acfDir . '/group_abc123.json', json_encode([
            'key' => 'group_abc123',
            'title' => 'My Field Group',
        ]));

        $manager = new AcfManager($this->tempDir);
        $manager->registerOptionsPages();

        $this->assertEmpty(AcfOptionsPageRecorder::$pages);
    }

    /**
     * Test registerOptionsPages discovers and registers a valid options page.
     */
    public function testRegisterOptionsPagesRegistersValidPage(): void
    {
        $acfDir = $this->tempDir . '/acf-json';
        mkdir($acfDir, 0777, true);
        file_put_contents($acfDir . '/options-page-site-settings.json', json_encode([
            'page_title' => 'Site Settings',
            'menu_title' => 'Site Settings',
            'menu_slug' => 'site-settings',
            'capability' => 'edit_posts',
            'redirect' => false,
        ]));

        $manager = new AcfManager($this->tempDir, 'my-theme');
        $manager->registerOptionsPages();

        $this->assertCount(1, AcfOptionsPageRecorder::$pages);
        $this->assertEquals('site-settings', AcfOptionsPageRecorder::$pages[0]['menu_slug']);
        $this->assertEquals('edit_posts', AcfOptionsPageRecorder::$pages[0]['capability']);
        $this->assertEmpty(AcfOptionsPageRecorder::$subPages);
    }

    /**
     * Test registerOptionsPages skips files with malformed JSON.
     */
    public function testRegisterOptionsPagesSkipsMalformedJson(): void
    {
        $acfDir = $this->tempDir . '/acf-json';
        mkdir($acfDir, 0777, true);
        file_put_contents($acfDir . '/options-page-broken.json', '{invalid json!!!}');

        $manager = new AcfManager($this->tempDir);
        $manager->registerOptionsPages();

        $this->assertEmpty(AcfOptionsPageRecorder::$pages);
    }

    /**
     * Test registerOptionsPages skips files missing menu_slug.
     */
    public function testRegisterOptionsPagesSkipsMissingMenuSlug(): void
    {
        $acfDir = $this->tempDir . '/acf-json';
        mkdir($acfDir, 0777, true);
        file_put_contents($acfDir . '/options-page-no-slug.json', json_encode([
            'page_title' => 'Missing Slug Page',
            'menu_title' => 'Missing Slug',
        ]));

        $manager = new AcfManager($this->tempDir);
        $manager->registerOptionsPages();

        $this->assertEmpty(AcfOptionsPageRecorder::$pages);
    }

    /**
     * Test registerOptionsPages routes sub-pages to acf_add_options_sub_page.
     */
    public function testRegisterOptionsPagesDetectsSubPage(): void
    {
        $acfDir = $this->tempDir . '/acf-json';
        mkdir($acfDir, 0777, true);
        file_put_contents($acfDir . '/options-page-sub-settings.json', json_encode([
            'page_title' => 'Sub Settings',
            'menu_title' => 'Sub Settings',
            'menu_slug' => 'sub-settings',
            'parent_slug' => 'site-settings',
            'capability' => 'edit_posts',
        ]));

        $manager = new AcfManager($this->tempDir);
        $manager->registerOptionsPages();

        $this->assertEmpty(AcfOptionsPageRecorder::$pages);
        $this->assertCount(1, AcfOptionsPageRecorder::$subPages);
        $this->assertEquals('sub-settings', AcfOptionsPageRecorder::$subPages[0]['menu_slug']);
        $this->assertEquals('site-settings', AcfOptionsPageRecorder::$subPages[0]['parent_slug']);
    }
}
