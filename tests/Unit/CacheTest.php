<?php

namespace Tests\Unit;

use System\Cache\FileStore;
use System\Cache\RateLimiter;
use System\Console\Generator;
use System\Database\Migrator;
use System\Testing\TestCase;

class CacheTest extends TestCase
{
    protected string $dir;
    protected FileStore $store;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_cache_' . bin2hex(random_bytes(4));
        $this->store = new FileStore($this->dir);
    }

    public function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f) : @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testPutGetForgetAndTypes(): void
    {
        $this->assertNull($this->store->get('missing'));
        $this->assertSame('fallback', $this->store->get('missing', 'fallback'));

        $this->store->put('arr', ['a' => 1, 'list' => [true, null]], 60);
        $this->assertSame(['a' => 1, 'list' => [true, null]], $this->store->get('arr'));
        $this->store->put('false', false, 60);
        $this->assertTrue($this->store->has('false'), 'false is a real value, not a miss');

        $this->assertTrue($this->store->forget('arr'));
        $this->assertFalse($this->store->has('arr'));
    }

    public function testExpiry(): void
    {
        $this->store->put('short', 'x', 1);
        $this->assertTrue(in_array($this->store->ttl('short'), [0, 1], true));
        sleep(2);
        $this->assertNull($this->store->get('short'), 'expired values are gone');

        $this->store->put('forever', 'y');
        $this->assertNull($this->store->ttl('forever'), 'null ttl = forever');
        $this->assertSame(0, $this->store->ttl('nope'));
    }

    public function testRememberComputesOnce(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return 'expensive';
        };
        $this->assertSame('expensive', $this->store->remember('r', 60, $compute));
        $this->assertSame('expensive', $this->store->remember('r', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function testIncrementKeepsExpiryAndFlush(): void
    {
        $this->assertSame(1, $this->store->increment('n', 1, 60));
        $this->assertSame(6, $this->store->increment('n', 5));
        $this->assertTrue($this->store->ttl('n') > 50);

        $this->store->put('old', 1, 1);
        sleep(2);
        $this->assertSame(1, $this->store->flush(true), 'flush(true) removes only expired entries');
        $this->assertSame(6, $this->store->get('n'));
        $this->assertSame(1, $this->store->flush());
        $this->assertSame([], glob("$this->dir/*") ?: [], 'a full flush leaves no lock files or empty folders');
    }

    public function testExpiredFlushRemovesOnlyStaleLockFiles(): void
    {
        $this->store->increment('fresh');
        $this->store->increment('old');
        $old = "$this->dir/" . sha1('old') . '.lock';
        $fresh = "$this->dir/" . sha1('fresh') . '.lock';
        $this->assertTrue(is_file($old) && is_file($fresh), 'increment locks the key with a .lock file');

        touch($old, time() - 7200);
        $this->store->flush(true);
        clearstatcache();
        $this->assertFalse(is_file($old), 'a lock unused for 2 hours is removed');
        $this->assertTrue(is_file($fresh), 'a recently used lock is kept');
        $this->assertSame(1, $this->store->get('old'), 'the value itself is untouched');
    }

    public function testRateLimiter(): void
    {
        $limiter = new RateLimiter($this->store);
        for ($i = 0; $i < 3; $i++) {
            $limiter->hit('login:1', 60);
        }
        $this->assertTrue($limiter->tooManyAttempts('login:1', 3));
        $this->assertFalse($limiter->tooManyAttempts('login:2', 3), 'keys are independent');
        $this->assertSame(2, $limiter->remaining('login:1', 5));
        $this->assertTrue($limiter->availableIn('login:1') > 50);
        $limiter->clear('login:1');
        $this->assertSame(0, $limiter->attempts('login:1'));
    }

    public function testGeneratorsWriteValidPhpAndNeverOverwrite(): void
    {
        $gen = new Generator($this->dir);
        $files = [
            $gen->controller('Admin/report'),
            $gen->controller('Api/Coupon', true),
            $gen->model('OrderItem'),
            $gen->middleware('EnsureIsAdmin'),
            $gen->test('CouponApi'),
            $gen->test('Price', true),
        ];
        $this->assertSame([
            'app/Http/Controllers/Admin/ReportController.php',
            'app/Http/Controllers/Api/CouponController.php',
            'app/Models/OrderItem.php',
            'app/Http/Middleware/EnsureIsAdmin.php',
            'tests/Feature/CouponApiTest.php',
            'tests/Unit/PriceTest.php',
        ], $files);

        foreach ($files as $file) {
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$this->dir/$file"), $out, $code);
            $this->assertSame(0, $code, "$file must be valid PHP");
        }

        $this->assertContains('namespace App\\Http\\Controllers\\Admin;', file_get_contents("$this->dir/app/Http/Controllers/Admin/ReportController.php"));
        $this->assertContains("protected static \$table = 'order_items';", file_get_contents("$this->dir/app/Models/OrderItem.php"));
        $this->assertThrows(\RuntimeException::class, fn() => $gen->model('OrderItem'));
        $this->assertThrows(\RuntimeException::class, fn() => $gen->controller('../evil'));
        $this->assertSame('categories', $gen->tableName('Category'));
        $this->assertSame('boxes', $gen->tableName('Box'));
    }

    public function testNewMigrationsSortAfterExistingOnes(): void
    {
        mkdir($this->dir . '/m', 0777, true);
        file_put_contents($this->dir . '/m/2999_01_01_000005_future.php', '<?php');
        $file = (new Migrator($this->dir . '/m'))->make('create_things_table');
        $this->assertContains('2999_01_01_000006_create_things_table.php', str_replace('\\', '/', $file));
    }
}
