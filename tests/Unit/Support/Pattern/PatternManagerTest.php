<?php

namespace Mythus\Tests\Unit\Support\Pattern;

use Mythus\Support\Pattern\PatternManager;
use WorDBless\BaseTestCase;
use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;

/**
 * Unit tests for the PatternManager class.
 */
class PatternManagerTest extends BaseTestCase
{
    private string $tempDir;

    public function set_up(): void
    {
        parent::set_up();
        $this->tempDir = sys_get_temp_dir() . '/pattern-manager-test-' . uniqid();
    }

    /** @var string[] Category slugs registered during a test, for cleanup. */
    private array $registeredCategories = [];

    public function tear_down(): void
    {
        // Unregister any patterns we registered during tests
        $registry = WP_Block_Patterns_Registry::get_instance();
        foreach ($registry->get_all_registered() as $pattern) {
            if (str_starts_with($pattern['name'], 'test-theme/')) {
                $registry->unregister($pattern['name']);
            }
        }

        // Unregister any categories we registered during tests
        $catRegistry = WP_Block_Pattern_Categories_Registry::get_instance();
        foreach ($this->registeredCategories as $slug) {
            if ($catRegistry->is_registered($slug)) {
                $catRegistry->unregister($slug);
            }
        }
        $this->registeredCategories = [];

        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }

        parent::tear_down();
    }

    public function testHasPatternsReturnsFalseWithoutDirectory(): void
    {
        $manager = new PatternManager('/nonexistent/path');

        $this->assertFalse($manager->hasPatterns());
    }

    public function testHasPatternsReturnsTrueWithDirectory(): void
    {
        mkdir($this->tempDir, 0777, true);
        $manager = new PatternManager($this->tempDir);

        $this->assertTrue($manager->hasPatterns());
    }

    public function testGetPatternsPathReturnsCorrectPath(): void
    {
        $manager = new PatternManager('/some/patterns/path');

        $this->assertEquals('/some/patterns/path', $manager->getPatternsPath());
    }

    public function testInitializeHooksSkipsWithoutDirectory(): void
    {
        $manager = new PatternManager('/nonexistent/path');
        $manager->initializeHooks();

        $this->assertFalse(has_action('init', [$manager, 'registerPatterns']));
    }

    public function testInitializeHooksRegistersInitAction(): void
    {
        mkdir($this->tempDir, 0777, true);
        $manager = new PatternManager($this->tempDir);
        $manager->initializeHooks();

        $this->assertNotFalse(has_action('init', [$manager, 'registerPatterns']));
    }

    public function testRegisterPatternsRegistersValidPattern(): void
    {
        mkdir($this->tempDir, 0777, true);
        $this->writePatternFile('test-pattern.php', 'Test Pattern', 'test-theme/test-pattern');

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertTrue($registry->is_registered('test-theme/test-pattern'));
    }

    public function testRegisterPatternsSkipsFileWithMissingTitle(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/no-title.php', "<?php\n/**\n * Slug: test-theme/no-title\n */\n?>\n<p>Content</p>");

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertFalse($registry->is_registered('test-theme/no-title'));
    }

    public function testRegisterPatternsSkipsFileWithMissingSlug(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/no-slug.php', "<?php\n/**\n * Title: No Slug Pattern\n */\n?>\n<p>Content</p>");

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        // Pattern shouldn't be registered without a slug
        $registry = WP_Block_Patterns_Registry::get_instance();
        $allPatterns = $registry->get_all_registered();
        $names = array_column($allPatterns, 'name');
        $this->assertNotContains('No Slug Pattern', $names);
    }

    public function testRegisterPatternsHandlesEmptyDirectory(): void
    {
        mkdir($this->tempDir, 0777, true);

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        // Should not error — no patterns to register
        $this->assertTrue(true);
    }

    public function testRegisterPatternsHandlesMultiplePatterns(): void
    {
        mkdir($this->tempDir, 0777, true);
        $this->writePatternFile('pattern-one.php', 'Pattern One', 'test-theme/pattern-one');
        $this->writePatternFile('pattern-two.php', 'Pattern Two', 'test-theme/pattern-two');

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertTrue($registry->is_registered('test-theme/pattern-one'));
        $this->assertTrue($registry->is_registered('test-theme/pattern-two'));
    }

    public function testRegisterPatternsParsesCategoriesAsArray(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/categorized.php', implode("\n", [
            '<?php',
            '/**',
            ' * Title: Categorized Pattern',
            ' * Slug: test-theme/categorized',
            ' * Categories: featured, header',
            ' */',
            '',
            '?>',
            '<p>Categorized content</p>',
        ]));

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertTrue($registry->is_registered('test-theme/categorized'));

        $pattern = $registry->get_registered('test-theme/categorized');
        $this->assertEquals(['featured', 'header'], $pattern['categories']);
    }

    public function testRegisterPatternsHandlesInserterFalse(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/hidden.php', implode("\n", [
            '<?php',
            '/**',
            ' * Title: Hidden Pattern',
            ' * Slug: test-theme/hidden',
            ' * Inserter: false',
            ' */',
            '',
            '?>',
            '<p>Hidden content</p>',
        ]));

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertTrue($registry->is_registered('test-theme/hidden'));

        $pattern = $registry->get_registered('test-theme/hidden');
        $this->assertFalse($pattern['inserter']);
    }

    public function testRegisterPatternsAutoRegistersCategories(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/categorized.php', implode("\n", [
            '<?php',
            '/**',
            ' * Title: Cat Test',
            ' * Slug: test-theme/cat-test',
            ' * Categories: custom-cat',
            ' */',
            '',
            '?>',
            '<p>Content</p>',
        ]));

        $this->registeredCategories[] = 'custom-cat';

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Pattern_Categories_Registry::get_instance();
        $this->assertTrue($registry->is_registered('custom-cat'));
    }

    public function testRegisterPatternsSkipsAlreadyRegisteredCategories(): void
    {
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/branded.php', implode("\n", [
            '<?php',
            '/**',
            ' * Title: Branded Test',
            ' * Slug: test-theme/branded-test',
            ' * Categories: my-brand',
            ' */',
            '',
            '?>',
            '<p>Content</p>',
        ]));

        // Pre-register with a custom label
        register_block_pattern_category('my-brand', [
            'label' => 'My Custom Brand',
        ]);
        $this->registeredCategories[] = 'my-brand';

        $manager = new PatternManager($this->tempDir, 'test-theme');
        $manager->registerPatterns();

        $registry = WP_Block_Pattern_Categories_Registry::get_instance();
        $registered = $registry->get_all_registered();

        $found = null;
        foreach ($registered as $cat) {
            if ($cat['name'] === 'my-brand') {
                $found = $cat;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertEquals('My Custom Brand', $found['label']);
    }

    /**
     * Write a simple pattern file to the temp directory.
     */
    private function writePatternFile(string $filename, string $title, string $slug): void
    {
        $content = implode("\n", [
            '<?php',
            '/**',
            ' * Title: ' . $title,
            ' * Slug: ' . $slug,
            ' */',
            '',
            '?>',
            '<p>' . $title . ' content</p>',
        ]);

        file_put_contents($this->tempDir . '/' . $filename, $content);
    }
}
