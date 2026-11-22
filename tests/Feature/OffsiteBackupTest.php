<?php

namespace Tests\Feature;

use App\Services\OffsiteBackupService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Deployment audit 2026-11-19: encrypted off-host backups with retention, proven to decrypt to the original. */
class OffsiteBackupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/offsite-'.bin2hex(random_bytes(4)));
        foreach (['backups', 'target', 'keys'] as $d) {
            mkdir($this->root.'/'.$d, 0700, true);
        }
        file_put_contents($this->root.'/keys/backup.key', base64_encode(random_bytes(48)));
        chmod($this->root.'/keys/backup.key', 0600);
        config([
            'backup.directory' => $this->root.'/backups',
            'backup.offsite.target' => $this->root.'/target',
            'backup.offsite.key_file' => $this->root.'/keys/backup.key',
            'backup.offsite.status_file' => $this->root.'/status.json',
            'backup.offsite.retention_days' => 7,
        ]);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();
        parent::tearDown();
    }

    /**
     * The service refuses a key file that group or others can read (chmod 600 is required). Windows ignores chmod, so
     * the key always looks readable by everyone there and every send is refused; such a test can only run on a file
     * system with owner-only permissions (the Linux server).
     */
    private function requireOwnerOnlyKeyFile(): void
    {
        clearstatcache(true, $this->root.'/keys/backup.key');
        if ((fileperms($this->root.'/keys/backup.key') & 0o077) !== 0) {
            $this->markTestSkipped('This file system ignores chmod 600 (Windows), so the key file cannot be made owner-only.');
        }
    }

    private function backup(string $name = 'iclimate_2026-11-20_0315.backup'): string
    {
        $path = $this->root.'/backups/'.$name;
        file_put_contents($path, "-- MariaDB dump 10.19\n--\nCREATE TABLE `users` (`id` int);\n".str_repeat("INSERT INTO `users` VALUES (1);\n", 200)."-- Dump completed on 2026-11-20  3:15:00\n");

        return $path;
    }

    public function test_the_newest_backup_is_encrypted_verified_copied_and_recorded(): void
    {
        $this->requireOwnerOnlyKeyFile();
        $original = $this->backup();

        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('Encrypted copy sent: iclimate_2026-11-20_0315.backup.enc')->assertSuccessful();

        $copy = $this->root.'/target/iclimate_2026-11-20_0315.backup.enc';
        $this->assertFileExists($copy);
        $this->assertStringNotContainsString('CREATE TABLE', (string) file_get_contents($copy), 'the copy is encrypted');
        $this->assertSame(0600, fileperms($copy) & 0777);
        [$cipherLine, $plainLine] = explode("\n", trim((string) file_get_contents($copy.'.sha256')));
        $this->assertSame(hash_file('sha256', $copy), strtok($cipherLine, ' '));
        $this->assertSame(hash_file('sha256', $original), strtok($plainLine, ' '));

        // The operator's restore step decrypts it back to the identical dump.
        $out = $this->root.'/restored.sql';
        $p = new Process(['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-md', 'sha256', '-in', $copy, '-out', $out, '-pass', 'file:'.$this->root.'/keys/backup.key']);
        $p->mustRun();
        $this->assertSame(hash_file('sha256', $original), hash_file('sha256', $out));

        $last = OffsiteBackupService::lastCopy();
        $this->assertSame('iclimate_2026-11-20_0315.backup.enc', $last['file']);
        $this->assertSame('directory', $last['target']);
    }

    public function test_an_unprotected_or_short_key_is_refused_and_nothing_is_sent(): void
    {
        $this->backup();
        chmod($this->root.'/keys/backup.key', 0644);
        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('must not be readable by group or others')->assertFailed();

        file_put_contents($this->root.'/keys/backup.key', 'short');
        chmod($this->root.'/keys/backup.key', 0600);
        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('shorter than 32 bytes')->assertFailed();
        $this->assertSame([], glob($this->root.'/target/*') ?: []);
        $this->assertNull(OffsiteBackupService::lastCopy());
    }

    public function test_old_copies_are_pruned_but_the_newest_is_kept(): void
    {
        $this->requireOwnerOnlyKeyFile();
        $old = $this->root.'/target/iclimate_2026-10-01_0315.backup.enc';
        file_put_contents($old, 'old');
        file_put_contents($old.'.sha256', 'x');
        touch($old, time() - 40 * 86400);
        $this->backup();

        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('Older copies removed: 1')->assertSuccessful();
        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist($old.'.sha256');
        $this->assertFileExists($this->root.'/target/iclimate_2026-11-20_0315.backup.enc');
    }

    public function test_without_configuration_nothing_happens_and_it_says_so(): void
    {
        config(['backup.offsite.target' => '', 'backup.offsite.key_file' => '']);
        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('not configured')->assertSuccessful();
    }

    public function test_a_target_inside_the_backup_directory_is_refused(): void
    {
        $this->requireOwnerOnlyKeyFile();       // the key is checked first, so on Windows the refusal names the key instead
        $this->backup();
        config(['backup.offsite.target' => $this->root.'/backups/offsite']);
        $this->artisan('iclimate:backup-offsite')->expectsOutputToContain('must not be inside the backup directory')->assertFailed();
    }
}
