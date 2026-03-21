<?php

namespace Mythus\Tests\Unit\Support\Asset;

use WorDBless\BaseTestCase;
use Mythus\Support\Asset\AssetManager;

/**
 * Unit tests for the AssetManager class.
 */
class AssetManagerTest extends BaseTestCase
{
    /**
     * Test that slugify removes Provider suffix and converts to kebab-case.
     */
    public function testSlugifyRemovesProviderSuffix(): void
    {
        $this->assertEquals('block', AssetManager::slugify('BlockProvider'));
    }

    /**
     * Test slugify with AssetProvider.
     */
    public function testSlugifyForAssetProvider(): void
    {
        $this->assertEquals('asset', AssetManager::slugify('AssetProvider'));
    }

    /**
     * Test slugify with simple name (no suffix).
     */
    public function testSlugifyWithNoSuffix(): void
    {
        $this->assertEquals('assets', AssetManager::slugify('Assets'));
    }

    /**
     * Test slugify preserves lowercase.
     */
    public function testSlugifyIsLowercase(): void
    {
        $slug = AssetManager::slugify('MyCustomBlockProvider');

        $this->assertEquals('my-custom-block', $slug);
        $this->assertStringNotContainsString('M', $slug);
        $this->assertStringNotContainsString('C', $slug);
        $this->assertStringNotContainsString('B', $slug);
    }

    /**
     * Test slugify with ThemeProvider (current naming convention).
     */
    public function testSlugifyForThemeProvider(): void
    {
        $this->assertEquals('theme', AssetManager::slugify('ThemeProvider'));
    }

    /**
     * Test slugify with ProjectProvider.
     */
    public function testSlugifyForProjectProvider(): void
    {
        $this->assertEquals('project', AssetManager::slugify('ProjectProvider'));
    }

    /**
     * Test constructor sets properties correctly via path methods.
     */
    public function testConstructorSetsProperties(): void
    {
        $manager = new AssetManager('theme', '/path/to/dist', 'https://example.com/dist');

        $this->assertEquals('https://example.com/dist/css/style.css', $manager->getStyleUri('style.css'));
        $this->assertEquals('https://example.com/dist/js/theme/script.js', $manager->getScriptUri('script.js'));
    }

    /**
     * Test getStylePath returns null when file doesn't exist.
     */
    public function testGetStylePathReturnsNullWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertNull($manager->getStylePath('nonexistent.css'));
    }

    /**
     * Test getScriptPath returns null when file doesn't exist.
     */
    public function testGetScriptPathReturnsNullWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertNull($manager->getScriptPath('nonexistent.js'));
    }

    /**
     * Test hasStyle returns false when file doesn't exist.
     */
    public function testHasStyleReturnsFalseWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertFalse($manager->hasStyle('nonexistent.css'));
    }

    /**
     * Test hasScript returns false when file doesn't exist.
     */
    public function testHasScriptReturnsFalseWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertFalse($manager->hasScript('nonexistent.js'));
    }

    /**
     * Test enqueueDistStyle does not enqueue when file doesn't exist.
     */
    public function testEnqueueDistStyleSkipsWhenFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueDistStyle('test-handle', 'blocks/style-index.css');

        $this->assertFalse(wp_style_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueDistScript does not enqueue when file doesn't exist.
     */
    public function testEnqueueDistScriptSkipsWhenFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueDistScript('test-handle', 'js/frontend.js');

        $this->assertFalse(wp_script_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueManifestScript returns early when asset file doesn't exist.
     */
    public function testEnqueueManifestScriptSkipsWhenAssetFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueManifestScript('test-handle', 'blocks/index.js');

        $this->assertFalse(wp_script_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueDistStyle constructs correct URI from dist-relative path.
     */
    public function testEnqueueDistStyleConstructsCorrectUri(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-test-dist';
        @mkdir($distPath . '/blocks', 0777, true);
        file_put_contents($distPath . '/blocks/style-index.css', 'body{}');

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueDistStyle('test-blocks-style', 'blocks/style-index.css');

        $this->assertTrue(wp_style_is('test-blocks-style', 'enqueued'));

        // Clean up
        @unlink($distPath . '/blocks/style-index.css');
        @rmdir($distPath . '/blocks');
        @rmdir($distPath);
    }

    /**
     * Test enqueueDistScript constructs correct URI from dist-relative path.
     */
    public function testEnqueueDistScriptConstructsCorrectUri(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-test-dist';
        @mkdir($distPath . '/js', 0777, true);
        file_put_contents($distPath . '/js/frontend.js', '// js');

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueDistScript('test-frontend-js', 'js/frontend.js');

        $this->assertTrue(wp_script_is('test-frontend-js', 'enqueued'));

        // Clean up
        @unlink($distPath . '/js/frontend.js');
        @rmdir($distPath . '/js');
        @rmdir($distPath);
    }

    /**
     * Test enqueueManifestScript loads script with manifest dependencies.
     */
    public function testEnqueueManifestScriptUsesDependenciesFromManifest(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-manifest-test-' . uniqid();
        @mkdir($distPath . '/blocks', 0777, true);

        // Create JS file
        file_put_contents($distPath . '/blocks/index.js', '// block js');

        // Create .asset.php manifest
        $manifest = [
            'dependencies' => ['wp-blocks', 'wp-element', 'wp-editor'],
            'version' => '1.2.3',
        ];
        file_put_contents(
            $distPath . '/blocks/index.asset.php',
            '<?php return ' . var_export($manifest, true) . ';'
        );

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueManifestScript('test-manifest-script', 'blocks/index.js');

        $this->assertTrue(wp_script_is('test-manifest-script', 'enqueued'));

        // Clean up
        @unlink($distPath . '/blocks/index.js');
        @unlink($distPath . '/blocks/index.asset.php');
        @rmdir($distPath . '/blocks');
        @rmdir($distPath);
    }

    /**
     * Test enqueueManifestScript merges extra dependencies with manifest deps.
     */
    public function testEnqueueManifestScriptMergesExtraDependencies(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-merge-deps-' . uniqid();
        @mkdir($distPath . '/blocks', 0777, true);

        file_put_contents($distPath . '/blocks/editor.js', '// editor js');

        $manifest = [
            'dependencies' => ['wp-blocks'],
            'version' => '2.0.0',
        ];
        file_put_contents(
            $distPath . '/blocks/editor.asset.php',
            '<?php return ' . var_export($manifest, true) . ';'
        );

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueManifestScript('test-merge-deps', 'blocks/editor.js', ['custom-dep', 'another-dep']);

        $this->assertTrue(wp_script_is('test-merge-deps', 'enqueued'));

        // Clean up
        @unlink($distPath . '/blocks/editor.js');
        @unlink($distPath . '/blocks/editor.asset.php');
        @rmdir($distPath . '/blocks');
        @rmdir($distPath);
    }

    /**
     * Test enqueueManifestScript skips when JS file exists but manifest doesn't.
     */
    public function testEnqueueManifestScriptSkipsWhenOnlyJsFileExists(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-no-manifest-' . uniqid();
        @mkdir($distPath . '/blocks', 0777, true);

        // Create JS file but no manifest
        file_put_contents($distPath . '/blocks/view.js', '// view js');

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueManifestScript('test-no-manifest', 'blocks/view.js');

        // Should not be enqueued because manifest is missing
        $this->assertFalse(wp_script_is('test-no-manifest', 'enqueued'));

        // Clean up
        @unlink($distPath . '/blocks/view.js');
        @rmdir($distPath . '/blocks');
        @rmdir($distPath);
    }
}
