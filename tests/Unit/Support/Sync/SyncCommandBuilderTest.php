<?php

declare(strict_types=1);

namespace Mythus\Tests\Unit\Support\Sync;

use Mythus\Support\Sync\SyncCommandBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Guards the sync exec command — specifically the regression fix where a PHP-FPM
 * worker parked at an inaccessible CWD (e.g. /root) with a cleared env made wp-cli's
 * launched subprocesses die with "posix_spawn()/sh: Permission denied".
 */
final class SyncCommandBuilderTest extends TestCase
{
    private const WP  = '/usr/local/bin/wp';
    private const ABS = '/var/www/example/public/wp';

    public function test_pins_path_cwd_and_home_before_the_wp_invocation(): void
    {
        $cmd = SyncCommandBuilder::build(self::WP, 'production', 'marc', self::ABS);

        // The fix: cd (accessible CWD) + PATH (FPM clears the env) + HOME must all
        // precede the wp call so every launched subprocess can resolve its binaries
        // from a directory www-data can actually access.
        $this->assertStringStartsWith(
            "cd '" . self::ABS . "' && PATH='/usr/local/bin:/usr/bin:/bin' HOME='" . self::ABS . "' ",
            $cmd
        );
    }

    public function test_targets_the_canonical_command_path_and_flags(): void
    {
        $cmd = SyncCommandBuilder::build(self::WP, 'production', 'marc', self::ABS);

        $this->assertStringContainsString("mythus sync-from 'production'", $cmd);
        $this->assertStringContainsString('--yes', $cmd);
        $this->assertStringContainsString("--path='" . self::ABS . "'", $cmd);
        $this->assertStringContainsString('--allow-root', $cmd);
        $this->assertStringEndsWith('2>&1', $cmd);
    }

    public function test_escapes_the_actor_to_prevent_command_injection(): void
    {
        $actor = "x'; rm -rf / #";
        $cmd   = SyncCommandBuilder::build(self::WP, 'production', $actor, self::ABS);

        $this->assertStringContainsString('--actor=' . escapeshellarg($actor), $cmd);
    }

    public function test_escapes_the_source_argument(): void
    {
        $source = "prod'; touch pwned";
        $cmd    = SyncCommandBuilder::build(self::WP, $source, 'marc', self::ABS);

        $this->assertStringContainsString('sync-from ' . escapeshellarg($source), $cmd);
    }
}
