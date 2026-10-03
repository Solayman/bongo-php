<?php
namespace Tests\Unit;

require_once BASE_PATH . "/release/tools/commands.php";

use System\Console\Packager;
use System\Console\ReleaseBuilder;
use System\Console\Upgrader;
use System\Testing\TestCase;

class UpgraderTest extends TestCase
{
    protected string $tmp;

    public function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/bongo_upgrade_' . bin2hex(random_bytes(4));
        mkdir($this->tmp);
    }

    public function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f) : @unlink($f);
        @rmdir($this->tmp);
    }

    /** An "old" project: built from this repo, then made to look like version 0.8.0 */
    protected function oldProject(): string
    {
        $project = "$this->tmp/app";
        (new ReleaseBuilder(BASE_PATH))->build($project);
        file_put_contents("$project/system/VERSION", "0.8.0\n");
        file_put_contents("$project/system/Removed.php", '<?php // a framework file the new version no longer has');
        file_put_contents("$project/app/Http/Controllers/HomeController.php", '<?php // my own code');
        file_put_contents("$project/config/mail.php", "<?php return ['driver' => 'log'];");   // missing newer keys
        return $project;
    }

    public function testUpgradeReplacesOnlyTheFrameworkAndCanBeRolledBack(): void
    {
        $package = (new Packager(BASE_PATH))->package("$this->tmp/dist");
        $sha = strtok((string) file_get_contents("$package.sha256"), ' ');
        $project = $this->oldProject();

        $result = (new Upgrader($project))->apply($package, $sha);

        $this->assertSame('0.8.0', $result['from']);
        $this->assertSame(\System\Framework::version(), $result['to']);
        $this->assertSame(\System\Framework::version(), trim(file_get_contents("$project/system/VERSION")));
        $this->assertFalse(is_file("$project/system/Removed.php"), 'framework files the new version dropped are gone');
        $this->assertSame('<?php // my own code', file_get_contents("$project/app/Http/Controllers/HomeController.php"), 'your code is never touched');
        $this->assertSame("<?php return ['driver' => 'log'];", file_get_contents("$project/config/mail.php"), 'your settings are never touched');
        $this->assertArrayHasKey('mail.php', $result['newConfig'], 'new settings are reported');
        $this->assertTrue(in_array('from', $result['newConfig']['mail.php'], true));
        $this->assertTrue(is_file($result['backup'] . '/system/Removed.php'), 'the backup has the old framework');

        // Undo
        $this->assertSame('0.8.0', (new Upgrader($project))->rollback());
        $this->assertSame('0.8.0', trim(file_get_contents("$project/system/VERSION")));
        $this->assertTrue(is_file("$project/system/Removed.php"));
    }

    public function testBadDownloadsChangeNothing(): void
    {
        $package = (new Packager(BASE_PATH))->package("$this->tmp/dist");
        $project = $this->oldProject();

        $this->assertThrows(\RuntimeException::class, fn() => (new Upgrader($project))->apply($package, str_repeat('0', 64)));
        file_put_contents("$this->tmp/evil.json.gz", gzencode(json_encode(['version' => '9.9.9', 'files' => ['app/Http/Controllers/HomeController.php' => base64_encode('<?php // hacked')]])));
        $this->assertThrows(\RuntimeException::class, fn() => (new Upgrader($project))->apply("$this->tmp/evil.json.gz"));
        file_put_contents("$this->tmp/junk.json.gz", 'not a package');
        $this->assertThrows(\RuntimeException::class, fn() => (new Upgrader($project))->apply("$this->tmp/junk.json.gz"));

        $this->assertSame('0.8.0', trim(file_get_contents("$project/system/VERSION")), 'still the old version');
        $this->assertSame('<?php // my own code', file_get_contents("$project/app/Http/Controllers/HomeController.php"));
    }
}
