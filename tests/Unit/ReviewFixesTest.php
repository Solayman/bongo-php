<?php
namespace Tests\Unit;

use System\Data\Printable;
use System\Data\Spreadsheet;
use System\Database\Model;
use System\Log\Logger;
use System\Storage\DownloadResponse;
use System\Database\Schema\Blueprint;
use System\Testing\TestCase;

class ReviewAlpha extends Model {}
class ReviewBeta extends Model {}

/** Regression tests for issues found in the 2026-10-04 review */
class ReviewFixesTest extends TestCase
{
    public function testSetConnectionNameOnlyMovesThatModel(): void
    {
        ReviewAlpha::setConnectionName('tenant');
        try {
            $this->assertSame('tenant', ReviewAlpha::getConnectionName());
            $this->assertNull(ReviewBeta::getConnectionName(), 'other models keep their connection');
            $this->assertNull(Model::getConnectionName());
        } finally {
            ReviewAlpha::setConnectionName(null);
        }
    }

    public function testBlueprintQuotesForItsOwnDatabase(): void
    {
        // Whatever the default connection is, each blueprint uses its own driver's quotes
        $this->assertContains('CREATE TABLE `posts`', (new Blueprint('posts', 'mysql'))->toCreateSql());
        $sqlite = new Blueprint('posts', 'sqlite');
        $sqlite->id();
        $this->assertContains('CREATE TABLE `posts`', $sqlite->toCreateStatements()[0]);
        $this->assertSame('"a""b"', Blueprint::wrap('a"b', 'pgsql'));
        $this->assertSame('`a``b`', Blueprint::wrap('a`b', 'mysql'));
    }

    public function testCsvCellsCannotRunFormulasInExcel(): void
    {
        $csv = Spreadsheet::toCsv([['name' => '=HYPERLINK("http://evil","x")', 'qty' => -5, 'note' => '@SUM(A1)', 'ok' => 'Ana']]);
        $this->assertContains("'=HYPERLINK", $csv);
        $this->assertContains("'@SUM", $csv);
        $this->assertContains(',-5,', $csv, 'real negative numbers stay numbers');
        $this->assertNotContains("'Ana", $csv);
    }

    public function testXlsxSurvivesControlCharactersAndBadUtf8(): void
    {
        $xlsx = Spreadsheet::toXlsx([['text' => "bell\x07 and bad \xC3\x28 byte", 'n' => NAN]]);
        $tmp = tempnam(sys_get_temp_dir(), 'bongo_t_') . '.xlsx';
        file_put_contents($tmp, $xlsx);
        try {
            $rows = Spreadsheet::readXlsx($tmp);
            $this->assertContains('bell and bad', (string) $rows[0]['text']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testPrintableEscapesEverythingThatReachesHtml(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn() => Printable::invoice('1')->badge('Paid', 'red;"><script>x</script>'));
        $html = Printable::invoice('1')
            ->customer(['name' => '<b>Ana</b>'])
            ->table(['A'], [['x']], ['50"><script>'], ['right"><script>'])
            ->render();
        $this->assertNotContains('<script>', $html);
        $this->assertNotContains('<b>Ana</b>', $html);
    }

    public function testDownloadFilenamesAreSafeHeaders(): void
    {
        $header = DownloadResponse::disposition("../a\"b\r\nX-Evil: 1 চালান.pdf");
        $this->assertNotContains("\n", $header);
        $this->assertNotContains('../', $header);
        $this->assertContains("filename*=UTF-8''", $header);
        $this->assertContains(rawurlencode('চালান'), $header);
    }

    public function testLoggerMasksSecretLikeKeysAndCannotBeForged(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_log_' . bin2hex(random_bytes(4));
        $logger = new Logger($dir, 'single');
        $logger->info("hello\n[2026-01-01 00:00:00] production.EMERGENCY: fake", [
            'api_key' => 'K1', 'access_token' => 'T1', 'client_secret' => 'S1', 'new_password' => 'P1', 'user' => 'ana',
        ]);
        $text = implode('', array_map('file_get_contents', glob("$dir/*.log")));
        foreach (['K1', 'T1', 'S1', 'P1'] as $secret) {
            $this->assertNotContains($secret, $text);
        }
        $this->assertContains('ana', $text);
        $this->assertNotContains("\n[2026-01-01", $text, 'a message cannot start a new log entry');
        array_map('unlink', glob("$dir/*"));
        @rmdir($dir);
    }
}
