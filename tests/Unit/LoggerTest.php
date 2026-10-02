<?php
namespace Tests\Unit;

use System\Log\Log;
use System\Log\Logger;
use System\Testing\TestCase;

class LoggerTest extends TestCase
{
    protected string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_logs_' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*.log') ?: []);
        @rmdir($this->dir);
        Logger::setInstance(null);
    }

    protected function logger(string $level = 'debug', string $channel = 'daily'): Logger
    {
        return new Logger($this->dir, $channel, $level, 14, 'testing');
    }

    public function testWritesFormattedLineToDailyFile(): void
    {
        $logger = $this->logger();
        $logger->info('Order {id} shipped to {city}', ['id' => 42, 'city' => 'Dhaka']);

        $this->assertSame($this->dir . '/bongo-' . date('Y-m-d') . '.log', $logger->file());
        $line = file_get_contents($logger->file());
        $this->assertContains('testing.INFO: Order 42 shipped to Dhaka {"id":42,"city":"Dhaka"}', $line);
        $this->assertTrue((bool) preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line));
    }

    public function testRespectsMinimumLevel(): void
    {
        $logger = $this->logger('warning');
        $logger->debug('hidden');
        $logger->info('hidden too');
        $logger->error('shown');

        $log = file_get_contents($logger->file());
        $this->assertNotContains('hidden', $log);
        $this->assertContains('ERROR: shown', $log);
    }

    public function testMasksSecretsInContext(): void
    {
        $logger = $this->logger();
        $logger->warning('Login', ['email' => 'a@b.co', 'password' => 'hunter2', 'nested' => ['token' => 'abc123']]);

        $log = file_get_contents($logger->file());
        $this->assertNotContains('hunter2', $log);
        $this->assertNotContains('abc123', $log);
        $this->assertContains('"password":"********"', $log);
        $this->assertContains('a@b.co', $log);
    }

    public function testLogsExceptionWithTrace(): void
    {
        $logger = $this->logger();
        $logger->error('Failed', ['exception' => new \RuntimeException('Boom')]);

        $log = file_get_contents($logger->file());
        $this->assertContains('RuntimeException: Boom in ', $log);
        $this->assertContains('#0 ', $log);
    }

    public function testSingleChannelAndNone(): void
    {
        $single = $this->logger('debug', 'single');
        $single->info('one file');
        $this->assertSame($this->dir . '/bongo.log', $single->file());
        $this->assertContains('one file', file_get_contents($single->file()));

        $none = new Logger($this->dir . '/none', 'none');
        $none->error('nothing');
        $this->assertFalse(is_dir($this->dir . '/none'));
    }

    public function testStaticFacadeAndHelperUseSharedLogger(): void
    {
        Logger::setInstance($this->logger());
        Log::notice('via facade');
        logger('via helper');
        logger()->critical('via instance');

        $log = file_get_contents(Logger::instance()->file());
        $this->assertContains('NOTICE: via facade', $log);
        $this->assertContains('DEBUG: via helper', $log);
        $this->assertContains('CRITICAL: via instance', $log);
    }

    public function testPhpWarningsAreLogged(): void
    {
        Logger::setInstance($this->logger());
        $handler = set_error_handler(fn() => false);
        restore_error_handler();
        $handler(E_WARNING, 'Undefined variable $x', __FILE__, 10);

        $this->assertContains('WARNING: PHP Undefined variable $x', file_get_contents(Logger::instance()->file()));
    }

    public function testOldDailyFilesArePruned(): void
    {
        @mkdir($this->dir, 0775, true);
        $old = $this->dir . '/bongo-' . date('Y-m-d', strtotime('-30 days')) . '.log';
        file_put_contents($old, "old\n");

        $this->logger()->info('new');
        $this->assertFalse(file_exists($old), 'Files older than the retention period are deleted');
    }

    public function testRecentReturnsNewestEntriesWithDetailsAndLevelFilter(): void
    {
        $logger = $this->logger();
        $logger->debug('first');
        $logger->warning('second');
        $logger->error('third', ['exception' => new \RuntimeException('Boom')]);

        $all = $logger->recent();
        $this->assertSame(['third', 'second', 'first'], array_column($all, 'message'));
        $this->assertSame('error', $all[0]['level']);
        $this->assertContains('RuntimeException: Boom', $all[0]['details']);

        $this->assertSame(['third', 'second'], array_column($logger->recent(10, 'warning'), 'message'));
        $this->assertCount(1, $logger->recent(1));
        $this->assertSame([], $this->logger('debug', 'single')->recent(), 'No file yet means no entries');
    }
}
