<?php

declare(strict_types=1);

namespace Mythus\Support\Asset;

/**
 * Manages asset path resolution and enqueueing for service providers.
 *
 * Styles are loaded from:  dist/css/{filename}
 * Scripts are loaded from: dist/js/{provider-slug}/{filename}
 */
class AssetManager
{
    public function __construct(
        private readonly string $slug,
        private readonly string $distPath,
        private readonly string $distUri,
    ) {}

    /**
     * Convert a PascalCase class name to a kebab-case slug.
     *
     * Removes the "Provider" suffix before converting.
     * Example: "BlockProvider" → "block"
     */
    public static function slugify(string $className): string
    {
        $name = preg_replace('/Provider$/', '', $className);

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
    }

    /**
     * Get the provider slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Check if a style file exists in the dist directory.
     */
    public function hasStyle(string $filename): bool
    {
        return file_exists($this->distPath . '/css/' . $filename);
    }

    /**
     * Check if a script file exists in the dist directory.
     */
    public function hasScript(string $filename): bool
    {
        return file_exists($this->distPath . '/js/' . $this->slug . '/' . $filename);
    }

    /**
     * Get the full path to a style file in dist/css.
     */
    public function getStylePath(string $filename): ?string
    {
        $path = $this->distPath . '/css/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full URI to a style file in dist/css.
     */
    public function getStyleUri(string $filename): ?string
    {
        return $this->distUri . '/css/' . $filename;
    }

    /**
     * Get the full path to a script file in dist/js/{provider-slug}/.
     */
    public function getScriptPath(string $filename): ?string
    {
        $path = $this->distPath . '/js/' . $this->slug . '/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full URI to a script file in dist/js/{provider-slug}/.
     */
    public function getScriptUri(string $filename): ?string
    {
        return $this->distUri . '/js/' . $this->slug . '/' . $filename;
    }

    /**
     * Enqueue a stylesheet from the dist/css directory.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $this->enqueueDistStyle($handle, 'css/' . $filename, $deps);
    }

    /**
     * Enqueue a script from the dist/js/{provider-slug}/ directory.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $this->enqueueDistScript($handle, 'js/' . $this->slug . '/' . $filename, $deps, $inFooter);
    }

    /**
     * Enqueue a stylesheet from any path relative to dist/.
     */
    public function enqueueDistStyle(string $handle, string $path, array $deps = []): void
    {
        $fullPath = $this->distPath . '/' . $path;
        $uri = $this->distUri . '/' . $path;

        if (file_exists($fullPath)) {
            wp_enqueue_style($handle, $uri, $deps, filemtime($fullPath));
        }
    }

    /**
     * Enqueue a script from any path relative to dist/.
     */
    public function enqueueDistScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $fullPath = $this->distPath . '/' . $path;
        $uri = $this->distUri . '/' . $path;

        if (file_exists($fullPath)) {
            wp_enqueue_script($handle, $uri, $deps, filemtime($fullPath), $inFooter);
        }
    }

    /**
     * Enqueue a script using an .asset.php manifest for dependencies and version.
     *
     * Expects a sibling .asset.php file next to the script (e.g. dist/blocks/index.asset.php
     * for dist/blocks/index.js). Returns early if the manifest file doesn't exist.
     *
     * @param string $handle    Unique handle for the script.
     * @param string $path      Path relative to dist/ (e.g. 'blocks/index.js').
     * @param array  $extraDeps Optional. Additional dependencies to merge with manifest deps.
     * @param bool   $inFooter  Optional. Whether to enqueue in footer. Default true.
     */
    public function enqueueManifestScript(string $handle, string $path, array $extraDeps = [], bool $inFooter = true): void
    {
        $assetFile = $this->distPath . '/' . preg_replace('/\.js$/', '.asset.php', $path);

        if (!file_exists($assetFile)) {
            return;
        }

        $asset = require $assetFile;
        $deps = array_unique([...$asset['dependencies'], ...$extraDeps]);
        $uri = $this->distUri . '/' . $path;

        wp_enqueue_script($handle, $uri, $deps, $asset['version'], $inFooter);
    }
}
