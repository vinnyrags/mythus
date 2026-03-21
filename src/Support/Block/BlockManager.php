<?php

declare(strict_types=1);

namespace Mythus\Support\Block;

/**
 * Manages block registration and editor script enqueueing for service providers.
 *
 * Searches one or more directories for blocks, supporting parent/child theme
 * inheritance. Search paths are derived automatically by the Provider from
 * the class hierarchy — child paths come first so child blocks can override
 * parent blocks of the same name.
 *
 * When both child and parent directories contain a block.json, the child's
 * file is treated as a partial override — its fields are merged on top of
 * the parent's metadata. This avoids duplicating the entire block.json in
 * child themes when only a few fields differ.
 */
class BlockManager
{
    /**
     * @param string[] $searchPaths Directories to search for blocks (child-first order).
     * @param string   $distPath    Absolute path to the active theme's dist/ directory.
     * @param string   $distUri     URI to the active theme's dist/ directory.
     * @param string[] $blocks      Block slugs to register.
     */
    public function __construct(
        private readonly array $searchPaths,
        private readonly string $distPath,
        private readonly string $distUri,
        private readonly array $blocks = [],
    ) {}

    /**
     * Get the block slugs to register.
     *
     * @return string[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Register all blocks.
     *
     * Searches each path in order for each block's block.json.
     * When a child path has a block.json and a parent path also has one,
     * the child's metadata is merged on top of the parent's before registration.
     * Called on the 'init' action hook.
     */
    public function registerBlocks(): void
    {
        foreach ($this->blocks as $block) {
            $this->registerBlock($block);
        }
    }

    /**
     * Register a single block, merging child overrides onto parent metadata.
     */
    private function registerBlock(string $block): void
    {
        $locations = $this->findBlockLocations($block);

        if (empty($locations)) {
            return;
        }

        // Single location — register directly from the directory.
        if (count($locations) === 1) {
            register_block_type($locations[0]);
            return;
        }

        // Multiple locations — child is first, parent is second.
        // Register from the parent directory (complete block.json), then use
        // a one-time filter to merge child overrides and redirect relative
        // file paths to the child directory.
        $childDir = $locations[0];
        $parentDir = $locations[1];

        $childMeta = $this->readBlockJson($childDir . '/block.json');

        if ($childMeta === null) {
            register_block_type($parentDir);
            return;
        }

        $childBlockJson = wp_normalize_path(realpath($childDir . '/block.json'));

        $filter = static function (array $metadata) use ($childMeta, $childBlockJson, &$filter): array {
            remove_filter('block_type_metadata', $filter, 99);

            $metadata = array_replace_recursive($metadata, $childMeta);

            // Redirect file paths so render.php etc. resolve from the child directory.
            $metadata['file'] = $childBlockJson;

            return $metadata;
        };

        add_filter('block_type_metadata', $filter, 99);
        register_block_type($parentDir);
    }

    /**
     * Find all directories containing block.json for a given block slug.
     *
     * Returns paths in search order (child-first).
     *
     * @return string[]
     */
    private function findBlockLocations(string $block): array
    {
        $locations = [];

        foreach ($this->searchPaths as $searchPath) {
            $blockDir = $searchPath . '/' . $block;

            if (file_exists($blockDir . '/block.json')) {
                $locations[] = $blockDir;
            }
        }

        return $locations;
    }

    /**
     * Read and decode a block.json file.
     */
    private function readBlockJson(string $path): ?array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    /**
     * Enqueue a block editor script from dist/js/.
     *
     * Block editor scripts are output directly to dist/js/ (not in a subdirectory)
     * and include standard WordPress block editor dependencies.
     */
    public function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
        $path = $this->distPath . '/js/' . $filename;
        $uri = $this->distUri . '/js/' . $filename;

        if (!file_exists($path)) {
            return;
        }

        $defaultDeps = [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-data',
        ];

        $allDeps = array_unique([...$defaultDeps, ...$deps]);

        wp_enqueue_script($handle, $uri, $allDeps, filemtime($path), true);
    }

    /**
     * Initialize block registration and asset hooks.
     *
     * @param object $provider The provider instance whose enqueueBlockAssets/enqueueBlockEditorAssets methods will be hooked.
     */
    public function initializeHooks(object $provider): void
    {
        if (empty($this->blocks)) {
            return;
        }

        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_assets', [$provider, 'enqueueBlockAssets']);
        add_action('enqueue_block_editor_assets', [$provider, 'enqueueBlockEditorAssets']);
    }
}
