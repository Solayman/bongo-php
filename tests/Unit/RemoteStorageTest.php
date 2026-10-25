<?php

namespace Tests\Unit;

use System\Storage\Drivers\FtpDriver;
use System\Storage\Drivers\SftpDriver;
use System\Storage\Storage;
use System\Testing\TestCase;

class RemoteStorageTest extends TestCase
{
    public function setUp(): void
    {
        FtpDriver::stopFaking();
        SftpDriver::stopFaking();
    }

    public function tearDown(): void
    {
        FtpDriver::stopFaking();
        SftpDriver::stopFaking();
    }

    public function testFtpDriverTimeoutClamping(): void
    {
        $driver = new FtpDriver('ftp_backup', [
            'host'            => '127.0.0.1',
            'connect_timeout' => 30, // Should be clamped to 4
            'timeout'         => 60, // Should be clamped to 8
        ]);

        $this->assertSame(4, $driver->getConnectTimeout(), 'Connect timeout must be clamped to max 4 seconds');
        $this->assertSame(8, $driver->getTimeout(), 'Operation timeout must be clamped to max 8 seconds');
    }

    public function testFtpDriverChrootJailPathFallback(): void
    {
        $driver = new FtpDriver('ftp_chroot', [
            'host' => '127.0.0.1',
            'root' => '/home/johndoe/public_html/storage',
        ]);

        // Default path before chroot detection
        $this->assertSame('/home/johndoe/public_html/storage/uploads/file.txt', $driver->resolveRemotePath('uploads/file.txt'));

        // Emulate chroot jail detected (e.g. CWD 550 fallback to relative/jailed path)
        $driver->setChrootJailPrefix('public_html/storage');
        $this->assertSame('public_html/storage', $driver->getChrootJailPrefix());
        $this->assertSame('public_html/storage/uploads/file.txt', $driver->resolveRemotePath('uploads/file.txt'));
    }

    public function testFtpDriverFakeFilesystemOperations(): void
    {
        FtpDriver::fake(['notes.txt' => 'Initial note']);
        $this->assertTrue(FtpDriver::isFaking());

        $driver = new FtpDriver('ftp_fake', ['host' => '127.0.0.1']);

        $this->assertTrue($driver->exists('notes.txt'));
        $this->assertSame('Initial note', $driver->get('notes.txt'));
        $this->assertSame(12, $driver->size('notes.txt'));

        $this->assertTrue($driver->put('documents/report.pdf', 'PDF content'));
        $this->assertTrue($driver->exists('documents/report.pdf'));
        $this->assertSame('PDF content', $driver->get('documents/report.pdf'));

        $files = $driver->files('');
        $this->assertTrue(in_array('notes.txt', $files, true));

        $this->assertTrue($driver->delete('notes.txt'));
        $this->assertFalse($driver->exists('notes.txt'));
    }

    public function testSftpDriverTimeoutClampingAndHostKeyBypass(): void
    {
        $driver = new SftpDriver('sftp_mirror', [
            'host'            => 'frs.sourceforge.net',
            'connect_timeout' => 20, // Clamped to 4
            'timeout'         => 45, // Clamped to 8
            'hostkey_verify'  => false,
        ]);

        $this->assertSame(4, $driver->getConnectTimeout(), 'Connect timeout clamped to 4');
        $this->assertSame(8, $driver->getTimeout(), 'Operation timeout clamped to 8');
        $this->assertFalse($driver->isHostKeyVerify(), 'Non-interactive hostkey verify is false');
    }

    public function testSftpDriverFakeFilesystemOperations(): void
    {
        SftpDriver::fake(['mirror/release.zip' => 'binary-data']);
        $this->assertTrue(SftpDriver::isFaking());

        $driver = new SftpDriver('sftp_fake', ['host' => '127.0.0.1']);

        $this->assertTrue($driver->exists('mirror/release.zip'));
        $this->assertSame('binary-data', $driver->get('mirror/release.zip'));
        $this->assertSame(11, $driver->size('mirror/release.zip'));

        $this->assertTrue($driver->put('mirror/checksum.txt', 'sha256-hash'));
        $this->assertSame('sha256-hash', $driver->get('mirror/checksum.txt'));

        $this->assertTrue($driver->delete('mirror/checksum.txt'));
        $this->assertFalse($driver->exists('mirror/checksum.txt'));
    }

    public function testStorageDiskResolutionForFtpAndSftp(): void
    {
        $GLOBALS['config']['storage']['disks']['custom_ftp'] = [
            'driver' => 'ftp',
            'host'   => 'ftp.example.com',
        ];
        $GLOBALS['config']['storage']['disks']['custom_sftp'] = [
            'driver' => 'sftp',
            'host'   => 'sftp.example.com',
        ];

        $ftpDisk = Storage::disk('custom_ftp');
        $this->assertTrue($ftpDisk instanceof FtpDriver);

        $sftpDisk = Storage::disk('custom_sftp');
        $this->assertTrue($sftpDisk instanceof SftpDriver);
    }
}
