<?php

namespace Tests\Unit;

use System\Database\Connection;
use System\Database\DB;
use System\Testing\TestCase;

class PerformanceToolsTest extends TestCase
{
    public function tearDown(): void
    {
        DB::disableQueryLog();
        parent::tearDown();
    }

    public function testRepeatedQueriesAreReportedAsPossibleNPlusOne(): void
    {
        DB::enableQueryLog();
        DB::recordQuery('default', 'select * from users where id = ?', [1], 0.1);
        foreach ([1, 2, 3] as $id) {
            DB::recordQuery('default', 'select * from orders where user_id = ?', [$id], 0.1);
        }
        DB::recordQuery('default', 'select * from settings', [], 0.1);
        DB::recordQuery('default', 'select * from settings', [], 0.1);

        $stats = debug_stats();
        $this->assertSame(['select * from orders where user_id = ?' => 3], $stats['repeated'], 'only queries run 3+ times count');
        $this->assertSame(6, $stats['queries']);
    }

    public function testNoQueriesMeansNoWarning(): void
    {
        DB::enableQueryLog();
        $this->assertSame([], debug_stats()['repeated']);
    }

    public function testPersistentConnectionNeverKeepsAnUnfinishedTransaction(): void
    {
        $default = (string) config('database.default');
        $config = config("database.connections.$default");
        if (!in_array($config['driver'], ['mysql', 'pgsql'], true)) {
            $this->assertTrue(true);   // SQLite has no server connection to keep
            return;
        }
        Connection::addConnection('persist_probe', ['persistent' => true] + $config);
        try {
            $pdo = Connection::getInstance('persist_probe');
            $this->assertTrue((bool) $pdo->getAttribute(\PDO::ATTR_PERSISTENT), 'DB_PERSISTENT turns on PDO::ATTR_PERSISTENT');
            $pdo->beginTransaction();
            unset($pdo);
            Connection::reset('persist_probe');

            $this->assertFalse(Connection::getInstance('persist_probe')->inTransaction(), 'the reused connection starts clean');
        } finally {
            Connection::removeConnection('persist_probe');
        }
    }

    public function testCacheForSendsHeadersAndAnswers304WhenUnchanged(): void
    {
        $cgi = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'php-cgi.exe' : 'php-cgi');
        if (!is_file($cgi)) {
            $this->assertTrue(true);   // headers can only be read through a CGI process
            return;
        }
        $script = BASE_PATH . '/storage/testing/cache_for_probe.php';
        @mkdir(dirname($script), 0775, true);
        file_put_contents($script, '<?php require ' . var_export(BASE_PATH . '/config/bootstrap.php', true)
            . '; cache_for(300, etag: "v1"); echo "BODY";');
        try {
            $first = $this->cgi($cgi, $script, '');
            $this->assertContains('Cache-Control: private, max-age=300', $first);
            $this->assertContains('ETag: "' . sha1('v1') . '"', $first);
            $this->assertContains('BODY', $first);

            $second = $this->cgi($cgi, $script, '"' . sha1('v1') . '"');
            $this->assertContains('Status: 304', $second, 'unchanged: 304 Not Modified');
            $this->assertNotContains('BODY', $second, 'no body is sent again');

            $changed = $this->cgi($cgi, $script, '"old"');
            $this->assertContains('BODY', $changed, 'a different ETag gets the full page');
        } finally {
            @unlink($script);
        }
    }

    private function cgi(string $cgi, string $script, string $ifNoneMatch): string
    {
        $env = getenv() + ['REDIRECT_STATUS' => '200', 'SCRIPT_FILENAME' => $script, 'REQUEST_METHOD' => 'GET'];
        $env['HTTP_IF_NONE_MATCH'] = $ifNoneMatch;
        $process = proc_open([$cgi, '-d', 'cgi.force_redirect=0'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
        $out = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($process);
        return (string) $out;
    }
}
