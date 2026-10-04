<?php

namespace Tests\Unit;

use System\Console\Generator;
use System\Testing\TestCase;

class GeneratorTest extends TestCase
{
    protected string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_gen_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f) : @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testNewGeneratorsWriteValidFiles(): void
    {
        $g = new Generator($this->dir);
        $files = [
            $g->policy('Product') => 'class ProductPolicy',
            $g->job('SendInvoice') => 'class SendInvoice extends Job',
            $g->seeder('Product') => 'class ProductSeeder',
            $g->notification('OrderShipped') => 'class OrderShipped extends Notification',
        ];
        foreach ($files as $file => $expected) {
            $path = "$this->dir/$file";
            $this->assertContains($expected, (string) file_get_contents($path));
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $out, $code);
            $this->assertSame(0, $code, "$file is valid PHP");
        }
        $this->assertSame('app/Policies/ProductPolicy.php', array_keys($files)[0]);

        $this->assertSame('resources/views/components/info-box.php', $g->component('InfoBox'));
        $this->assertContains('<x-info-box', (string) file_get_contents("$this->dir/resources/views/components/info-box.php"));
        $this->assertContains('resources/views/emails/order-shipped.php', $g->mail('OrderShipped'));

        $this->assertThrows(\RuntimeException::class, fn() => $g->job('SendInvoice'), 'never overwrites');
    }
}
