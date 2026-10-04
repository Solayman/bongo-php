<?php
namespace Tests\Unit;

use System\Database\Model;
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
}
