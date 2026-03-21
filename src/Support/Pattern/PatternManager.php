<?php

declare(strict_types=1);

namespace Mythus\Support\Pattern;

/**
 * Manages block pattern registration for providers.
 *
 * Auto-discovers .php pattern files in the provider's patterns/ directory.
 * Each file must contain standard WordPress pattern headers (Title, Slug).
 * Silent if the directory doesn't exist.
 */
class PatternManager
{
    private static array $headerMap = [
        'Title'          => 'title',
        'Slug'           => 'slug',
        'Categories'     => 'categories',
        'Description'    => 'description',
        'Viewport Width' => 'viewportWidth',
        'Inserter'       => 'inserter',
        'Keywords'       => 'keywords',
        'Block Types'    => 'blockTypes',
        'Post Types'     => 'postTypes',
        'Template Types' => 'templateTypes',
    ];

    public function __construct(
        private readonly string $patternsPath,
        private readonly string $textDomain = 'theme',
    ) {}

    /**
     * Whether this provider has a patterns directory.
     */
    public function hasPatterns(): bool
    {
        return is_dir($this->patternsPath);
    }

    /**
     * Get the patterns directory path.
     */
    public function getPatternsPath(): string
    {
        return $this->patternsPath;
    }

    /**
     * Register init hook for pattern registration.
     *
     * No-op if the patterns directory doesn't exist.
     */
    public function initializeHooks(): void
    {
        if (!$this->hasPatterns()) {
            return;
        }

        add_action('init', [$this, 'registerPatterns']);
    }

    /**
     * Discover and register all block patterns from the patterns directory.
     */
    public function registerPatterns(): void
    {
        $files = $this->discoverPatterns();
        $this->registerDiscoveredCategories($files);

        foreach ($files as $file) {
            try {
                $pattern = $this->parsePatternFile($file);

                if ($pattern === null) {
                    continue;
                }

                $slug = $pattern['slug'];
                unset($pattern['slug']);

                ob_start();
                include $file;
                $pattern['content'] = (string) ob_get_clean();

                register_block_pattern($slug, $pattern);
            } catch (\Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'PatternManager: Failed to register pattern from %s: %s',
                    $file,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Auto-register block pattern categories found in pattern file headers.
     *
     * Skips categories that are already registered, preserving custom labels
     * set earlier (e.g., in a provider's register() method).
     *
     * @param string[] $files Pattern file paths.
     */
    private function registerDiscoveredCategories(array $files): void
    {
        $registry = \WP_Block_Pattern_Categories_Registry::get_instance();

        $categories = [];
        foreach ($files as $file) {
            $headers = get_file_data($file, ['Categories' => 'Categories']);
            if (!empty($headers['Categories'])) {
                foreach (array_map('trim', explode(',', $headers['Categories'])) as $slug) {
                    if ($slug !== '') {
                        $categories[$slug] = true;
                    }
                }
            }
        }

        foreach (array_keys($categories) as $slug) {
            if (!$registry->is_registered($slug)) {
                register_block_pattern_category($slug, [
                    'label' => ucwords(str_replace('-', ' ', $slug)),
                ]);
            }
        }
    }

    /**
     * Discover all .php pattern files in the patterns directory.
     *
     * @return string[]
     */
    private function discoverPatterns(): array
    {
        $files = glob($this->patternsPath . '/*.php');

        return $files ?: [];
    }

    /**
     * Parse a pattern file's headers into a registration-ready array.
     *
     * Returns null if Title or Slug is missing.
     *
     * @return array<string, mixed>|null
     */
    private function parsePatternFile(string $filePath): ?array
    {
        $headers = get_file_data($filePath, array_combine(
            array_keys(self::$headerMap),
            array_keys(self::$headerMap),
        ));

        $title = $headers['Title'] ?? '';
        $slug = $headers['Slug'] ?? '';

        if ($title === '' || $slug === '') {
            return null;
        }

        $pattern = [
            'title' => __($title, $this->textDomain),
            'slug'  => $slug,
        ];

        if (!empty($headers['Description'])) {
            $pattern['description'] = __($headers['Description'], $this->textDomain);
        }

        if (!empty($headers['Categories'])) {
            $pattern['categories'] = array_map('trim', explode(',', $headers['Categories']));
        }

        if (!empty($headers['Keywords'])) {
            $pattern['keywords'] = array_map('trim', explode(',', $headers['Keywords']));
        }

        if (!empty($headers['Block Types'])) {
            $pattern['blockTypes'] = array_map('trim', explode(',', $headers['Block Types']));
        }

        if (!empty($headers['Post Types'])) {
            $pattern['postTypes'] = array_map('trim', explode(',', $headers['Post Types']));
        }

        if (!empty($headers['Template Types'])) {
            $pattern['templateTypes'] = array_map('trim', explode(',', $headers['Template Types']));
        }

        if (!empty($headers['Viewport Width'])) {
            $pattern['viewportWidth'] = (int) $headers['Viewport Width'];
        }

        if (isset($headers['Inserter']) && $headers['Inserter'] !== '') {
            $pattern['inserter'] = !in_array(
                strtolower($headers['Inserter']),
                ['no', 'false', '0'],
                true,
            );
        }

        return $pattern;
    }
}
