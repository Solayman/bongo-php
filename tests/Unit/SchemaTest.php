<?php

namespace Tests\Unit;

use System\Database\Migrator;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class SchemaTest extends TestCase
{
    public function testCompilesCreateTable(): void
    {
        $table = new Blueprint('posts');
        $table->id();
        $table->foreignId('user_id')->references('users')->onDelete('cascade');
        $table->string('title', 120);
        $table->string('slug')->unique();
        $table->boolean('published')->default(false);
        $table->enum('kind', ["news", "it's"])->default('news');
        $table->text('body')->nullable();
        $table->index(['published', 'kind']);

        $sql = $table->toCreateSql();

        $this->assertContains('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        $this->assertContains('`title` VARCHAR(120) NOT NULL', $sql);
        $this->assertContains("`published` TINYINT(1) NOT NULL DEFAULT 0", $sql);
        $this->assertContains("ENUM('news', 'it''s') NOT NULL DEFAULT 'news'", $sql);
        $this->assertContains('`body` TEXT NULL', $sql);
        $this->assertContains('PRIMARY KEY (`id`)', $sql);
        $this->assertContains('UNIQUE KEY `posts_slug_unique` (`slug`)', $sql);
        $this->assertContains('INDEX `posts_published_kind_index` (`published`, `kind`)', $sql);
        $this->assertContains('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
    }

    public function testCompilesAlterTable(): void
    {
        $table = new Blueprint('posts');
        $table->string('subtitle')->nullable()->after('title');
        $table->dropColumn('legacy');
        $table->renameColumn('body', 'content');

        $sql = $table->toAlterSql();
        $this->assertContains('ADD COLUMN `subtitle` VARCHAR(255) NULL AFTER `title`', $sql);
        $this->assertContains('DROP COLUMN `legacy`', $sql);
        $this->assertContains('RENAME COLUMN `body` TO `content`', $sql);
        $this->assertNull((new Blueprint('x'))->toAlterSql());
    }

    public function testInvalidForeignKeyActionIsRejected(): void
    {
        $table = new Blueprint('t');
        $this->assertThrows(\InvalidArgumentException::class, fn() => $table->foreignId('a')->references('b')->onDelete('explode'));
        $this->assertThrows(\LogicException::class, fn() => $table->foreignId('c')->onDelete('cascade'));
    }

    public function testCreateAlterAndDropAgainstTheDatabase(): void
    {
        Schema::dropIfExists('schema_probe');
        Schema::create('schema_probe', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $this->assertTrue(Schema::hasTable('schema_probe'));

        Schema::table('schema_probe', fn(Blueprint $table) => $table->integer('score')->default(0));
        $this->assertTrue(Schema::hasColumn('schema_probe', 'score'));

        Schema::drop('schema_probe');
        $this->assertFalse(Schema::hasTable('schema_probe'));
    }

    public function testMigrationsAreTrackedAndCanBeRolledBack(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_migrations_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/2099_01_01_000000_create_probe_items_table.php", <<<'PHP'
<?php
use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('probe_items', fn(Blueprint $t) => $t->id()); }
    public function down(): void { Schema::dropIfExists('probe_items'); }
};
PHP);

        try {
            $migrator = new Migrator($dir);

            $this->assertSame(['2099_01_01_000000_create_probe_items_table'], $migrator->migrate());
            $this->assertTrue(Schema::hasTable('probe_items'));
            $this->assertSame([], $migrator->migrate(), 'A migration must only run once');

            $status = array_column($migrator->status(), null, 'migration');
            $this->assertTrue($status['2099_01_01_000000_create_probe_items_table']['ran']);

            $this->assertSame(['2099_01_01_000000_create_probe_items_table'], $migrator->rollback());
            $this->assertFalse(Schema::hasTable('probe_items'));
            $this->assertArrayHasKey('2026_10_01_000001_create_users_table', $migrator->ran(), 'Earlier batches are untouched');
        } finally {
            Schema::dropIfExists('probe_items');
            \System\Database\DB::table('migrations')->where('migration', '2099_01_01_000000_create_probe_items_table')->delete();
            @unlink("$dir/2099_01_01_000000_create_probe_items_table.php");
            @rmdir($dir);
        }
    }

    public function testMakeMigrationWritesAStub(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_make_' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $file = (new Migrator($dir))->make('create_posts_table');
            $code = file_get_contents($file);
            $this->assertContains("Schema::create('posts'", $code);
            $this->assertContains("Schema::dropIfExists('posts')", $code);
            $this->assertThrows(\RuntimeException::class, fn() => (new Migrator($dir))->make('Bad Name'));
        } finally {
            array_map('unlink', glob("$dir/*.php"));
            @rmdir($dir);
        }
    }
}
