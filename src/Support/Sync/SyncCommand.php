<?php

declare(strict_types=1);

namespace Mythus\Support\Sync;

use WP_CLI;

/**
 * `wp mythus sync-from <staging|production>` — pull a higher environment's DB +
 * uploads DOWN into the current (lower) environment. One-way only: it only ever
 * reads a remote source and writes into the CURRENT env; production is never a
 * target. local ← staging/production; staging ← production; production ← none.
 *
 * Inert unless the consumer configures sources via the `mythus/sync/sources`
 * filter (see {@see SyncEnv}).
 */
final class SyncCommand
{
    /**
     * Exit code for the staleness refusal — distinct from the generic error (1) so
     * a UI driving this command can tell "the guard blocked me, offer an override"
     * apart from "something actually broke". Kept in sync with any REST/admin caller.
     */
    public const EXIT_STALE = 3;

    /**
     * Pull production/staging down into the current environment.
     *
     * ## OPTIONS
     *
     * <source>
     * : Environment to pull from. One of: staging, production.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--force]
     * : Override the staleness guard and pull even if the source is behind this
     *   environment (a deliberate rollback). Never set by the admin buttons.
     *
     * [--actor=<name>]
     * : Who triggered this pull, recorded in the activity log. Defaults to "wp-cli".
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function __invoke(array $args, array $assoc): void
    {
        $source = $args[0] ?? '';
        $actor  = $assoc['actor'] ?? 'wp-cli';
        $env    = SyncEnv::current();

        $allowed = SyncEnv::allowedSources();
        if ($allowed === []) {
            WP_CLI::error("Current env '{$env}' is not a valid sync target — production is never a target.");
        }
        if (!in_array($source, $allowed, true)) {
            WP_CLI::error("Invalid source '{$source}' for env '{$env}'. Allowed: " . implode(', ', $allowed) . '.');
        }

        $sources = SyncEnv::sources();
        if (!isset($sources[$source])) {
            WP_CLI::error("No configuration for source '{$source}'. Supply it via the mythus/sync/sources filter.");
        }
        $src       = $sources[$source];
        $onDroplet = in_array($env, ['staging', 'production'], true);
        $targetUrl = home_url();

        WP_CLI::log("Plan: pull {$source} ({$src['url']}) -> {$env} ({$targetUrl})");
        WP_CLI::log($onDroplet ? 'Transport: on-box (co-located)' : "Transport: over SSH ({$src['ssh']})");

        // Staleness guard — never SILENTLY pull a source that is behind this env
        // (e.g. clicking "Pull from Production" before launch, while staging is
        // still the source of truth). Compares the newest content edit on each
        // side; a stale source would roll this env back and discard newer edits.
        // The admin buttons never pass --force, so an accidental click is blocked;
        // a deliberate rollback is CLI-only (`--force`).
        $srcClock    = $this->contentClock($src, $onDroplet);
        $targetClock = $this->localContentClock();
        if (!isset($assoc['force']) && $srcClock !== '' && $targetClock !== '' && $srcClock < $targetClock) {
            WP_CLI::error(sprintf(
                "Refusing to pull: %s was last edited %s UTC, but this %s environment has NEWER content (%s UTC). "
                . 'Pulling would roll %s back and discard those newer edits. '
                . 'If this rollback is intentional, re-run with --force.',
                $source,
                $srcClock,
                $env,
                $targetClock,
                $env
            ), self::EXIT_STALE);
        }

        WP_CLI::confirm("This OVERWRITES the current ({$env}) database + uploads with {$source}. Continue?", $assoc);

        // 1. Snapshot the target first (rollback artifact); keep newest 3.
        $stamp     = gmdate('Ymd-His');
        $backupDir = SyncEnv::workDir();
        wp_mkdir_p($backupDir);
        $snapshot = "{$backupDir}/pre-sync-{$env}-{$stamp}.sql";
        WP_CLI::log("Snapshotting current DB -> {$snapshot}");
        WP_CLI::runcommand('db export ' . escapeshellarg($snapshot), ['launch' => true, 'exit_error' => true]);
        $this->pruneSnapshots($backupDir);

        // 2. Fetch source DB, then import.
        $dump = "{$backupDir}/source-{$source}-{$stamp}.sql";
        if ($onDroplet) {
            WP_CLI::launch(sprintf('wp db export %s --path=%s --allow-root', escapeshellarg($dump), escapeshellarg($src['wp_path'])));
        } else {
            WP_CLI::launch(sprintf(
                '%sssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 %s %s > %s',
                SyncEnv::sshEnv(),
                escapeshellarg($src['ssh']),
                escapeshellarg(sprintf('wp db export - --path=%s --allow-root', $src['wp_path'])),
                escapeshellarg($dump)
            ));
        }
        if (!file_exists($dump) || filesize($dump) < 1024) {
            WP_CLI::error("Source dump failed or empty ({$dump}). Target untouched — snapshot at {$snapshot}.");
        }
        WP_CLI::log("Importing {$source} DB into {$env}...");
        WP_CLI::runcommand('db import ' . escapeshellarg($dump), ['launch' => true, 'exit_error' => true]);
        @unlink($dump);

        // 3. Serialization-safe URL rewrite.
        WP_CLI::log("Rewriting URLs: {$src['url']} -> {$targetUrl}");
        WP_CLI::runcommand(
            sprintf('search-replace %s %s --all-tables --skip-columns=guid --report-changed-only', escapeshellarg($src['url']), escapeshellarg($targetUrl)),
            ['launch' => true, 'exit_error' => true]
        );

        // 4. Uploads (exclude the sync dir so snapshots/log never cross envs).
        $targetUploads = rtrim(wp_get_upload_dir()['basedir'], '/');
        WP_CLI::log('Syncing uploads...');
        if ($onDroplet) {
            WP_CLI::launch(sprintf('rsync -a --exclude=%s %s %s', escapeshellarg(SyncEnv::DOTDIR), escapeshellarg($src['uploads'] . '/'), escapeshellarg($targetUploads . '/')));
        } else {
            WP_CLI::launch(sprintf(
                '%srsync -az --exclude=%s -e %s %s %s',
                SyncEnv::sshEnv(),
                escapeshellarg(SyncEnv::DOTDIR),
                escapeshellarg('ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new'),
                escapeshellarg($src['ssh'] . ':' . $src['uploads'] . '/'),
                escapeshellarg($targetUploads . '/')
            ));
        }

        // 5. Re-assert non-prod noindex.
        if ($env !== 'production') {
            WP_CLI::runcommand('option update blog_public 0', ['launch' => true, 'exit_error' => false]);
        }

        // 6. Flush caches.
        WP_CLI::runcommand('cache flush', ['launch' => true, 'exit_error' => false]);
        $cacheDir = SyncEnv::cacheDir($env);
        if ($onDroplet && $cacheDir && strpos($cacheDir, '/var/cache/nginx/') === 0) {
            WP_CLI::launch('rm -rf ' . $cacheDir . '/*', false);
        }

        SyncEnv::appendLog([
            'time'   => gmdate('Y-m-d H:i:s') . ' UTC',
            'actor'  => $actor,
            'source' => $source,
            'target' => $env,
            'ok'     => true,
            'note'   => basename($snapshot),
        ]);

        WP_CLI::success("Synced {$source} -> {$env}. Rollback snapshot: {$snapshot}");
    }

    /** Newest content edit on the current (target) env as a GMT string, '' if unknown. */
    private function localContentClock(): string
    {
        global $wpdb;
        $val = $wpdb->get_var("SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}");

        return is_string($val) ? trim($val) : '';
    }

    /**
     * Newest content edit on a SOURCE env as a GMT string, read over the same
     * transport used for the pull (on-box wp-cli, or wp-cli over SSH). Returns
     * '' when it can't be read — the guard then fails open (allows the pull).
     *
     * @param array{url:string,ssh:string,wp_path:string,uploads:string} $src
     */
    private function contentClock(array $src, bool $onDroplet): string
    {
        $remote = sprintf(
            'wp db query %s --skip-column-names --path=%s --allow-root',
            escapeshellarg('SELECT MAX(post_modified_gmt) FROM wp_posts'),
            escapeshellarg($src['wp_path'])
        );

        if ($onDroplet) {
            $cmd = $remote . ' 2>/dev/null';
        } else {
            $cmd = sprintf(
                '%sssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 %s %s 2>/dev/null',
                SyncEnv::sshEnv(),
                escapeshellarg($src['ssh']),
                escapeshellarg($remote)
            );
        }

        return trim((string) @shell_exec($cmd));
    }

    /** Keep only the newest N pre-sync snapshots. */
    private function pruneSnapshots(string $dir, int $keep = 3): void
    {
        $snaps = glob("{$dir}/pre-sync-*.sql") ?: [];
        if (count($snaps) <= $keep) {
            return;
        }
        usort($snaps, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($snaps, $keep) as $old) {
            @unlink($old);
        }
    }
}
