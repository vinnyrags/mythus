<?php

declare(strict_types=1);

namespace Mythus;

use DI\Container;
use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use Mythus\Support\Acf\AcfManager;
use Mythus\Support\Asset\AssetManager;
use Mythus\Support\Block\BlockManager;
use Mythus\Support\AbstractRegistry;
use Mythus\Support\Feature\FeatureManager;
use Mythus\Support\Pattern\PatternManager;
use Mythus\Support\Rest\RestManager;
use ReflectionClass;

/**
 * Base provider class.
 *
 * All providers should extend this class and implement the register method.
 * Provides asset enqueueing via AssetManager and block registration via BlockManager.
 *
 * This is a framework-level provider with no Timber/Twig coupling.
 * Theme-specific providers should extend a bridge class that adds
 * Twig functions, template paths, and other presentation concerns.
 */
abstract class Provider implements Registrable
{
    /**
     * Feature classes to register (toggleable via => false).
     *
     * @var array<class-string<Registrable>>
     */
    protected array $features = [];

    /**
     * Hook classes to register (always-active, additive only).
     *
     * @var array<class-string<Registrable>>
     */
    protected array $hooks = [];

    /**
     * Blocks to register.
     *
     * @var string[]
     */
    protected array $blocks = [];

    /**
     * REST API endpoint classes to register.
     *
     * @var array<class-string<\Mythus\Contracts\Routable>>
     */
    protected array $routes = [];

    /**
     * REST API namespace prefix (defaults to provider slug).
     */
    protected string $routeNamespace = '';

    /**
     * REST API version string.
     */
    protected string $routeVersion = 'v1';

    protected ?AcfManager $acfManager = null;
    protected ?AssetManager $assets = null;
    protected ?BlockManager $blockManager = null;
    protected ?FeatureManager $featureManager = null;
    protected ?PatternManager $patternManager = null;
    protected ?RestManager $restManager = null;
    protected string $configPath;
    protected string $textDomain;

    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Register the service provider.
     *
     * Child classes should override this method and call parent::register()
     * to ensure features and blocks are registered.
     */
    public function register(): void
    {
        $this->setup();
        $this->registerHooks();
        $this->registerFeatures();
        $this->acfManager->initializeHooks();
        $this->blockManager->initializeHooks($this);
        $this->patternManager->initializeHooks();

        if (!empty($this->restManager->getEnabled())) {
            add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    /**
     * Set up the asset, block, feature, and REST managers.
     *
     * Called once by register(). Idempotent as a safety net.
     */
    protected function setup(): void
    {
        if ($this->assets !== null) {
            return;
        }

        $reflection = new ReflectionClass($this);
        $slug = AssetManager::slugify($reflection->getShortName());
        $providerDir = dirname($reflection->getFileName());

        $distPath = $this->getDistPath();
        $distUri = $this->getDistUri();

        $this->configPath = $providerDir . '/config';
        $this->textDomain = $this->resolveTextDomain($providerDir);
        $this->assets = new AssetManager($slug, $distPath, $distUri);

        $searchPaths = $this->getBlockSearchPaths();
        $allBlocks = $this->collectBlocks();

        $this->blockManager = new BlockManager($searchPaths, $distPath, $distUri, $allBlocks);
        $this->featureManager = new FeatureManager($this->collectFeatures(), $this->container);
        $this->acfManager = new AcfManager($providerDir, $this->textDomain);
        $this->patternManager = new PatternManager($providerDir . '/patterns', $this->textDomain);

        $routeNamespace = $this->routeNamespace ?: $slug;
        $this->restManager = new RestManager(
            $this->collectRoutes(),
            $this->container,
            $routeNamespace . '/' . $this->routeVersion,
        );
    }

    /**
     * Get the absolute filesystem path to the dist directory.
     *
     * Override in theme-specific bridge classes to resolve theme paths.
     */
    protected function getDistPath(): string
    {
        return get_stylesheet_directory() . '/dist';
    }

    /**
     * Get the URI to the dist directory.
     *
     * Override in theme-specific bridge classes to resolve theme URIs.
     */
    protected function getDistUri(): string
    {
        return get_stylesheet_directory_uri() . '/dist';
    }

    /**
     * Resolve the text domain for this provider.
     *
     * Override in theme-specific bridge classes for parent/child detection.
     *
     * @param string $providerDir Absolute path to the provider's directory.
     */
    protected function resolveTextDomain(string $providerDir): string
    {
        return 'theme';
    }

    /**
     * Register all hook classes (always-active behavioral wiring).
     *
     * Hooks are resolved from the DI container and registered unconditionally.
     * Errors are isolated per-hook so a single failure doesn't prevent others.
     */
    protected function registerHooks(): void
    {
        foreach ($this->collectHooks() as $hook) {
            try {
                $instance = $this->container->get($hook);

                if (!$instance instanceof Hook) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'Provider: %s does not implement Hook. '
                        . 'Move it to $features for toggleable behavior, '
                        . 'or implement Hook for always-active hooks.',
                        $hook
                    ));
                    continue;
                }

                $instance->register();
            } catch (\Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'Provider: Failed to register hook %s: %s',
                    $hook,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Register all feature classes via the FeatureManager.
     */
    protected function registerFeatures(): void
    {
        $this->featureManager->registerAll();
    }

    /**
     * Collect and merge features from the class hierarchy.
     *
     * @return array<class-string, bool>
     */
    protected function collectFeatures(): array
    {
        return $this->collectItems('features');
    }

    /**
     * Collect and merge hooks from the class hierarchy.
     *
     * Unlike features, hooks are additive only — no opt-out via => false.
     *
     * @return array<class-string>
     */
    protected function collectHooks(): array
    {
        return $this->collectAdditive('hooks');
    }

    /**
     * Collect and merge routes from the class hierarchy.
     *
     * @return array<class-string, bool>
     */
    protected function collectRoutes(): array
    {
        return $this->collectItems('routes');
    }

    /**
     * Collect and merge blocks from the class hierarchy (additive).
     *
     * Walks from the concrete class up toward Provider, merging and
     * deduplicating block slugs. Child blocks take priority.
     *
     * @return string[]
     */
    protected function collectBlocks(): array
    {
        return $this->collectAdditive('blocks');
    }

    /**
     * Build the list of block search paths from the class hierarchy.
     *
     * Walks from the concrete class upward, collecting each unique provider
     * directory that contains a blocks/ subdirectory. Child paths come first
     * so child blocks can override parent blocks of the same name.
     *
     * @return string[]
     */
    protected function getBlockSearchPaths(): array
    {
        $paths = [];
        $seen = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $dir = dirname($class->getFileName());

            if (!isset($seen[$dir])) {
                $seen[$dir] = true;
                $blocksPath = $dir . '/blocks';

                if (is_dir($blocksPath)) {
                    $paths[] = $blocksPath;
                }
            }

            $class = $class->getParentClass();
        }

        return $paths;
    }

    /**
     * Collect and merge a property from the class hierarchy.
     *
     * Walks from the concrete class up toward Provider, normalizing
     * each level's array into [class => bool]. Child entries override
     * parent entries, allowing opt-out via `ClassName::class => false`.
     *
     * @return array<class-string, bool>
     */
    private function collectItems(string $property): array
    {
        $merged = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $defaults = $class->getDefaultProperties();
            if (isset($defaults[$property])) {
                $normalized = AbstractRegistry::normalize($defaults[$property]);
                $merged = array_merge($normalized, $merged);
            }
            $class = $class->getParentClass();
        }

        return $merged;
    }

    /**
     * Collect and merge an additive property from the class hierarchy.
     *
     * Walks from the concrete class up toward Provider, producing a flat,
     * deduplicated array. No => false support — purely additive.
     *
     * @return array<class-string>
     */
    private function collectAdditive(string $property): array
    {
        $merged = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $defaults = $class->getDefaultProperties();
            if (isset($defaults[$property]) && is_array($defaults[$property])) {
                $merged = array_merge($merged, $defaults[$property]);
            }
            $class = $class->getParentClass();
        }

        return array_values(array_unique($merged));
    }

    /**
     * Register all REST API routes via the RestManager.
     */
    public function registerRoutes(): void
    {
        $this->restManager->registerAll();
    }

    /**
     * Register a post type from a JSON config file.
     *
     * Reads the config via loadConfig(), registers the post type, and
     * handles custom flags like "classic_editor": true.
     *
     * @return string|null The registered post type slug, or null on failure.
     */
    protected function registerPostTypeFromConfig(string $filename): ?string
    {
        $config = $this->loadConfig($filename);

        if (!$config || !isset($config['post_type'], $config['args'])) {
            return null;
        }

        $postType = $config['post_type'];
        $args = $config['args'];

        if (isset($args['labels'])) {
            $args['labels'] = $this->translateLabels($args['labels']);
        }

        register_post_type($postType, $args);

        if (!empty($config['classic_editor'])) {
            add_filter('use_block_editor_for_post_type', static function (bool $use, string $type) use ($postType): bool {
                return $type === $postType ? false : $use;
            }, 10, 2);
        }

        return $postType;
    }

    /**
     * Load a JSON configuration file from the provider's config directory.
     */
    protected function loadConfig(string $filename): ?array
    {
        $this->setup();

        $filepath = $this->configPath . '/' . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);

        if ($content === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('Provider::loadConfig: Could not read file: %s', $filepath));
            return null;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf(
                'Provider::loadConfig: Invalid JSON in %s: %s',
                $filepath,
                json_last_error_msg()
            ));
            return null;
        }

        return $data;
    }

    /**
     * Translate an array of label strings using the provider's text domain.
     *
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    protected function translateLabels(array $labels): array
    {
        $this->setup();

        $translated = [];

        foreach ($labels as $key => $label) {
            $translated[$key] = __($label, $this->textDomain);
        }

        return $translated;
    }

    /**
     * Enqueue a stylesheet from the dist/css directory.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $this->assets->enqueueStyle($handle, $filename, $deps);
    }

    /**
     * Enqueue a script from the dist/js/{provider-slug}/ directory.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueScript($handle, $filename, $deps, $inFooter);
    }

    /**
     * Enqueue a stylesheet from any path relative to dist/.
     */
    protected function enqueueDistStyle(string $handle, string $path, array $deps = []): void
    {
        $this->assets->enqueueDistStyle($handle, $path, $deps);
    }

    /**
     * Enqueue a script from any path relative to dist/.
     */
    protected function enqueueDistScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueDistScript($handle, $path, $deps, $inFooter);
    }

    /**
     * Enqueue a script using an .asset.php manifest for dependencies and version.
     */
    protected function enqueueManifestScript(string $handle, string $path, array $extraDeps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueManifestScript($handle, $path, $extraDeps, $inFooter);
    }

    /**
     * Enqueue a block editor script from dist/js/.
     */
    protected function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
        $this->blockManager->enqueueEditorScript($handle, $filename, $deps);
    }

    /**
     * Enqueue block assets for both frontend and editor.
     *
     * Override this method to enqueue styles/scripts for your blocks.
     * Called on 'enqueue_block_assets' hook.
     */
    public function enqueueBlockAssets(): void
    {
        // Override in provider to enqueue block assets
    }

    /**
     * Enqueue block editor assets only.
     *
     * Override this method to enqueue editor-only scripts for your blocks.
     * Called on 'enqueue_block_editor_assets' hook.
     */
    public function enqueueBlockEditorAssets(): void
    {
        // Override in provider to enqueue editor assets
    }

    /**
     * Get the blocks to register.
     *
     * @return string[]
     */
    public function getBlocks(): array
    {
        $this->setup();
        return $this->blockManager->getBlocks();
    }
}
