<?php
namespace Tests\Unit;

use System\Console\ReleaseBuilder;
use System\Testing\TestCase;

class ReleaseBuilderTest extends TestCase
{
    protected string $target;

    public function setUp(): void
    {
        $this->target = sys_get_temp_dir() . '/bongo_release_' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        if (!is_dir($this->target)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->target, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f) : @unlink($f);
        @rmdir($this->target);
    }

    public function testBuildsAFreshProjectWithoutTheDemoApp(): void
    {
        $count = (new ReleaseBuilder(BASE_PATH))->build($this->target);
        $this->assertTrue($count > 100, "$count files");

        foreach (['system/Framework.php', 'bongo', 'config/app.php', 'public/index.php', 'routes/web.php', 'app/Http/Controllers/HomeController.php',
                  'resources/views/welcome.php', 'resources/views/errors/404.php', 'database/seeders/DatabaseSeeder.php',
                  'database/migrations/2026_10_01_000001_create_users_table.php', 'tests/Feature/HomeTest.php', 'README.md'] as $file) {
            $this->assertTrue(is_file("$this->target/$file"), "has $file");
        }
        foreach (['app/Http/Controllers/ProductController.php', 'app/Models/Product.php', 'app/Services/Cart.php', 'resources/views/shop',
                  'database/migrations/2026_10_01_000003_create_products_table.php', 'release', 'TESTING_GUIDE.md'] as $demo) {
            $this->assertFalse(file_exists("$this->target/$demo"), "no $demo");
        }
        $this->assertNotContains('Product', (string) file_get_contents("$this->target/routes/web.php"));
        $this->assertTrue(is_dir("$this->target/storage/logs"));
    }

    public function testRefusesANonEmptyFolderOrOneInsideTheRepository(): void
    {
        $this->assertThrows(\RuntimeException::class, fn() => (new ReleaseBuilder(BASE_PATH))->build(BASE_PATH . '/tests'));
        $this->assertThrows(\RuntimeException::class, fn() => (new ReleaseBuilder(BASE_PATH))->build(BASE_PATH . '/new-app'));
    }
}
