<?php

declare(strict_types=1);

namespace Mythus\Support\Acf;

/**
 * Manages ACF JSON sync paths and options page auto-discovery for providers.
 *
 * Each provider gets its own AcfManager instance during setup().
 * If the provider has an acf-json/ directory, the manager registers
 * it as an ACF JSON load path and discovers options page definitions.
 * Silent if the directory doesn't exist.
 */
class AcfManager
{
    private readonly string $acfJsonPath;

    public function __construct(
        string $providerDir,
        private readonly string $textDomain = 'theme',
    ) {
        $this->acfJsonPath = $providerDir . '/acf-json';
    }

    /**
     * Whether this provider has an acf-json directory.
     */
    public function hasAcfJson(): bool
    {
        return is_dir($this->acfJsonPath);
    }

    /**
     * Get the acf-json directory path.
     */
    public function getAcfJsonPath(): string
    {
        return $this->acfJsonPath;
    }

    /**
     * Register ACF JSON load path filter and options page discovery.
     *
     * No-op if the acf-json directory doesn't exist.
     */
    public function initializeHooks(): void
    {
        if (!$this->hasAcfJson()) {
            return;
        }

        add_filter('acf/settings/load_json', [$this, 'addLoadPath']);
        add_action('acf/init', [$this, 'registerOptionsPages']);
    }

    /**
     * Add this provider's acf-json directory to ACF's load paths.
     *
     * @param string[] $paths Existing ACF JSON load paths.
     * @return string[]
     */
    public function addLoadPath(array $paths): array
    {
        $paths[] = $this->acfJsonPath;

        return $paths;
    }

    /**
     * Set this provider's acf-json directory as the ACF JSON save path.
     *
     * Call this in a provider's register() method to make this provider
     * the target for saving new or updated field groups.
     */
    public function registerSavePath(): void
    {
        if (!$this->hasAcfJson()) {
            return;
        }

        add_filter('acf/settings/save_json', fn () => $this->acfJsonPath);
    }

    /**
     * Discover and register ACF options pages from JSON files.
     *
     * Scans acf-json/ for files matching options-page-*.json and registers
     * each as an options page (or sub-page if parent_slug is present).
     */
    public function registerOptionsPages(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        foreach ($this->discoverOptionsPages() as $config) {
            $config = $this->translatePageLabels($config);

            if (isset($config['parent_slug'])) {
                acf_add_options_sub_page($config);
            } else {
                acf_add_options_page($config);
            }
        }
    }

    /**
     * Discover options page JSON files in the acf-json directory.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverOptionsPages(): array
    {
        $pattern = $this->acfJsonPath . '/options-page-*.json';
        $files = glob($pattern);

        if ($files === false || $files === []) {
            return [];
        }

        $pages = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf('AcfManager: Could not read file: %s', $file));
                continue;
            }

            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'AcfManager: Invalid JSON in %s: %s',
                    $file,
                    json_last_error_msg()
                ));
                continue;
            }

            if (!isset($config['menu_slug'])) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf('AcfManager: Missing menu_slug in %s', $file));
                continue;
            }

            $pages[] = $config;
        }

        return $pages;
    }

    /**
     * Translate page_title and menu_title using the provider's text domain.
     *
     * @param array<string, mixed> $config Options page configuration.
     * @return array<string, mixed>
     */
    private function translatePageLabels(array $config): array
    {
        foreach (['page_title', 'menu_title'] as $key) {
            if (isset($config[$key]) && is_string($config[$key])) {
                $config[$key] = __($config[$key], $this->textDomain);
            }
        }

        return $config;
    }
}
