<?php

declare(strict_types=1);

namespace Mythus\Support\Sync;

/**
 * Environment detection + config for the one-way sync engine, shared by the CLI
 * command ({@see SyncCommand}) and any admin UI that drives it — so the "which env
 * am I / what may I pull from / where does it live" logic lives in one place.
 *
 * The engine is **inert by default**: {@see sources()} is empty unless a consumer
 * supplies its droplet config via the `mythus/sync/sources` filter, so a site that
 * doesn't configure Sync simply has no valid pull sources and the CLI command errors
 * out cleanly. Cache dirs come from `mythus/sync/cache_dirs` the same way.
 */
final class SyncEnv
{
    /** Working dotdir under uploads (snapshots + activity log). Canonical, cross-site — uploads are per-site, so it never collides. */
    public const DOTDIR = '.mythus-sync';

    /** Which sources each env may pull FROM (downhill only; prod is never a target). */
    private const ALLOWED = [
        'local'      => ['staging', 'production'],
        'staging'    => ['production'],
        'production' => [],
    ];

    public static function current(): string
    {
        if (getenv('IS_DDEV_PROJECT') === 'true' || getenv('DDEV_PRIMARY_URL') || strpos((string) home_url(), '.ddev.site') !== false) {
            return 'local';
        }
        $type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

        return in_array($type, ['staging', 'production'], true) ? $type : 'local';
    }

    /**
     * @param  string|null $env Env to resolve for; defaults to the current env.
     * @return string[]
     */
    public static function allowedSources(?string $env = null): array
    {
        return self::ALLOWED[$env ?? self::current()] ?? [];
    }

    /**
     * Remote (droplet) source environments a consumer supplies via the
     * `mythus/sync/sources` filter. Empty by default → the engine is inert.
     *
     * @return array<string,array{url:string,ssh:string,wp_path:string,uploads:string}>
     */
    public static function sources(): array
    {
        $sources = apply_filters('mythus/sync/sources', []);

        return is_array($sources) ? $sources : [];
    }

    /**
     * nginx FastCGI page-cache dir to purge for a given target env, from the
     * `mythus/sync/cache_dirs` filter (`['staging' => '/var/cache/nginx/…']`).
     * Null when the target env runs uncached (e.g. production microcache).
     */
    public static function cacheDir(string $env): ?string
    {
        $dirs = apply_filters('mythus/sync/cache_dirs', []);

        return is_array($dirs) ? ($dirs[$env] ?? null) : null;
    }

    /**
     * `SSH_AUTH_SOCK=… ` prefix for launched ssh/rsync commands. WP_CLI::launch()
     * and php-fpm both spawn without the agent in-env; fall back to DDEV's fixed
     * agent socket for the CMS-button path. Empty when no agent (co-located path).
     */
    public static function sshEnv(): string
    {
        $sock = getenv('SSH_AUTH_SOCK');
        if (!$sock && is_readable('/home/.ssh-agent/socket')) {
            $sock = '/home/.ssh-agent/socket';
        }

        return $sock ? 'SSH_AUTH_SOCK=' . escapeshellarg($sock) . ' ' : '';
    }

    /** Working dir (snapshots + log) under the current env's uploads. */
    public static function workDir(): string
    {
        return rtrim(wp_get_upload_dir()['basedir'], '/') . '/' . self::DOTDIR;
    }

    /** Activity-log file (JSON, survives DB import; under the web-blocked dotdir). */
    public static function logPath(): string
    {
        return self::workDir() . '/sync-log.json';
    }

    /** @return array<int,array<string,mixed>> newest first */
    public static function readLog(): array
    {
        $path = self::logPath();
        if (!is_readable($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string,mixed> $entry
     */
    public static function appendLog(array $entry, int $keep = 20): void
    {
        $path = self::logPath();
        wp_mkdir_p(dirname($path));
        $log = self::readLog();
        array_unshift($log, $entry);
        file_put_contents($path, (string) wp_json_encode(array_slice($log, 0, $keep)));
    }
}
