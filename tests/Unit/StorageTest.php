<?php

namespace Tests\Unit;

use System\Http\UploadedFile;
use System\Storage\DownloadResponse;
use System\Storage\Storage;
use System\Testing\TestCase;

class StorageTest extends TestCase
{
    public function tearDown(): void
    {
        Storage::disk('public')->delete('testing/hello.txt');
        Storage::disk('public')->delete('testing/copy.txt');
        Storage::disk('local')->delete('private/secret.txt');
        Storage::disk('public')->deleteDirectory('testing');
        Storage::disk('local')->deleteDirectory('private');
        Storage::reset();
        parent::tearDown();
    }

    public function testDefaultDiskOperatesOnPublicStorage(): void
    {
        $this->assertTrue(Storage::put('testing/hello.txt', 'Hello World'));
        $this->assertTrue(Storage::exists('testing/hello.txt'));
        $this->assertFalse(Storage::missing('testing/hello.txt'));
        $this->assertSame('Hello World', Storage::get('testing/hello.txt'));
        $this->assertSame(11, Storage::size('testing/hello.txt'));
        $this->assertNotNull(Storage::url('testing/hello.txt'));
        $this->assertContains('/storage/testing/hello.txt', Storage::url('testing/hello.txt'));

        $this->assertTrue(Storage::delete('testing/hello.txt'));
        $this->assertFalse(Storage::exists('testing/hello.txt'));
    }

    public function testPrivateLocalDiskIsolation(): void
    {
        $local = Storage::disk('local');
        $this->assertTrue($local->put('private/secret.txt', 'Top Secret Data'));
        $this->assertTrue($local->exists('private/secret.txt'));
        $this->assertSame('Top Secret Data', $local->get('private/secret.txt'));

        // It is not in public disk
        $this->assertFalse(Storage::disk('public')->exists('private/secret.txt'));

        // Calling url() on private disk throws exception
        $this->assertThrows(\RuntimeException::class, fn() => $local->url('private/secret.txt'));

        // Download response object
        $response = $local->download('private/secret.txt', 'exported_secret.txt');
        $this->assertTrue($response instanceof DownloadResponse);
        $this->assertSame('exported_secret.txt', $response->filename);
        $this->assertTrue(is_file($response->path));
    }

    public function testDirectoryListingAndDeletion(): void
    {
        $disk = Storage::disk('public');
        $disk->put('testing/a.txt', 'A');
        $disk->put('testing/b.txt', 'B');
        $disk->put('testing/nested/c.txt', 'C');

        $files = $disk->files('testing');
        $this->assertTrue(in_array('testing/a.txt', $files, true));
        $this->assertTrue(in_array('testing/b.txt', $files, true));

        $all = $disk->allFiles('testing');
        $this->assertTrue(in_array('testing/a.txt', $all, true));
        $this->assertTrue(in_array('testing/nested/c.txt', $all, true));

        $this->assertTrue($disk->deleteDirectory('testing'));
        $this->assertFalse($disk->exists('testing/a.txt'));
    }

    public function testUploadedFileStoresToSelectedDisk(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bongo_test_');
        file_put_contents($tmp, 'Sample text file');
        $file = UploadedFile::fake($tmp, 'document.txt');

        $path = $file->store('private_docs', 'local');
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertSame('Sample text file', Storage::disk('local')->get($path));

        Storage::disk('local')->delete($path);
        Storage::disk('local')->deleteDirectory('private_docs');
        @unlink($tmp);
    }

    public function testCustomDriverExtensionAndSet(): void
    {
        $customCalled = false;
        Storage::extend('custom_mock', function (array $config, string $name) use (&$customCalled) {
            $customCalled = true;
            return new \System\Storage\Disk($name, array_merge(['root' => BASE_PATH . '/storage/app'], $config));
        });

        // Set custom disk config in runtime
        $GLOBALS['config']['storage']['disks']['mock_disk'] = ['driver' => 'custom_mock'];

        $disk = Storage::disk('mock_disk');
        $this->assertTrue($customCalled);
        $this->assertTrue($disk instanceof \System\Storage\Disk);

        // Test Storage::set() override
        $explicit = new \System\Storage\Disk('explicit', ['root' => BASE_PATH . '/storage/app']);
        Storage::set('explicit_disk', $explicit);
        $this->assertSame($explicit, Storage::disk('explicit_disk'));
    }

    public function testTemporaryUrlForwarding(): void
    {
        $url = Storage::temporaryUrl('testing/file.txt', time() + 300);
        $this->assertNotNull($url);
        $this->assertContains('testing/file.txt', $url);
    }
}
