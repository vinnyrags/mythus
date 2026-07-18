<?php

declare(strict_types=1);

namespace Mythus\Support\Sync;

/**
 * Builds the shell command an admin "pull" button shells out to.
 *
 * A pure, WordPress-free unit so the two things that actually bite here can be
 * asserted in isolation:
 *
 *  1. PATH. PHP-FPM runs with clear_env=yes (the default), so exec() inherits an
 *     empty environment — no PATH. Without it PHP can't resolve its own binary
 *     (PHP_BINARY comes back empty), so wp-cli's launched subprocess for the
 *     snapshot db export builds an empty command and dies with
 *     "sh: 1: : Permission denied". A minimal PATH lets wp-cli find php/mysqldump.
 *  2. The CWD/HOME prefix. PHP-FPM workers can be parked at a working directory
 *     www-data cannot access (e.g. /root), and exec() inherits it. wp-cli's
 *     launched subprocesses then die with
 *     "proc_open(): posix_spawn() failed: Permission denied". Pinning an
 *     accessible CWD (and HOME) means every child inherits a usable directory.
 *  3. Argument escaping — the source/actor/paths are shell-escaped so they can
 *     never break out of the command.
 */
final class SyncCommandBuilder
{
    /** Minimal PATH for the launched wp-cli subprocesses (php, mysqldump, rsync, ssh). */
    private const PATH = '/usr/local/bin:/usr/bin:/bin';

    /** The wp-cli command the engine registers (see SyncProvider). */
    private const COMMAND = 'mythus sync-from';

    /**
     * @param string $wp      Absolute path to the wp-cli binary.
     * @param string $source  Source env to pull from (already gate-checked upstream).
     * @param string $actor   Who triggered the pull (recorded in the activity log).
     * @param string $abspath WordPress ABSPATH — the accessible CWD/HOME and --path.
     * @param bool   $force   Override the staleness guard (a deliberate rollback). A
     *                        UI must only set this after an explicit, informed confirm.
     */
    public static function build(string $wp, string $source, string $actor, string $abspath, bool $force = false): string
    {
        return sprintf(
            'cd %s && PATH=%s HOME=%s %s %s %s --yes%s --actor=%s --path=%s --allow-root 2>&1',
            escapeshellarg($abspath),
            escapeshellarg(self::PATH),
            escapeshellarg($abspath),
            escapeshellarg($wp),
            self::COMMAND,
            escapeshellarg($source),
            $force ? ' --force' : '',
            escapeshellarg($actor),
            escapeshellarg($abspath)
        );
    }
}
