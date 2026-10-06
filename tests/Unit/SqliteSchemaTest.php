<?php

namespace Tests\Unit;

use PDO;
use System\Database\Schema\Blueprint;
use System\Testing\TestCase;

/**
 * The same migration code compiled for SQLite. The SQL is checked always;
 * it is also run against an in-memory database when pdo_sqlite is enabled.
 */
class SqliteSchemaTest extends TestCase
{
    protected function posts(): Blueprint
    {
        $table = new Blueprint('posts', 'sqlite');
        $table->id();
        $table->foreignId('user_id')->nullable()->references('users')->onDelete('cascade');
        $table->string('slug')->unique();
        $table->boolean('published')->default(false);
        $table->enum('kind', ['news', "it's"])->default('news');
        $table->json('meta')->nullable();
        $table->index(['published', 'kind']);
        $table->timestamps();
        return $table;
    }

    public function testCompilesCreateTableForSqlite(): void
    {
        $sql = implode(";\n", $this->posts()->toCreateStatements());

        $this->assertContains('`id` INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $this->assertContains("`kind` TEXT NOT NULL DEFAULT 'news' CHECK (`kind` IN ('news', 'it''s'))", $sql);
        $this->assertContains('`meta` TEXT NULL', $sql);
        $this->assertContains('CREATE UNIQUE INDEX `posts_slug_unique` ON `posts` (`slug`)', $sql);
        $this->assertContains('CREATE INDEX `posts_published_kind_index` ON `posts` (`published`, `kind`)', $sql);
        $this->assertContains('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
        $this->assertContains('CREATE TRIGGER IF NOT EXISTS `posts_updated_at_on_update`', $sql);
        $this->assertNotContains('AUTO_INCREMENT', $sql);
        $this->assertNotContains('UNSIGNED', $sql);
        $this->assertNotContains('ENGINE', $sql);
    }

    public function testCompilesCompositePrimaryKeyForSqlite(): void
    {
        $table = new Blueprint('role_has_permissions', 'sqlite');
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('permission_id');
        $table->primary(['role_id', 'permission_id']);

        $statements = $table->toCreateStatements();
        $sql = implode(";\n", $statements);
        $this->assertContains('PRIMARY KEY (`role_id`, `permission_id`)', $sql);
    }

    public function testAlterGivesOneStatementPerChange(): void
    {
        $table = new Blueprint('posts', 'sqlite');
        $table->string('subtitle')->nullable()->after('title');
        $table->renameColumn('body', 'content');
        $table->dropColumn('legacy');
        $table->dropIndex('posts_old_index');

        $this->assertSame([
            'ALTER TABLE `posts` DROP COLUMN `legacy`',
            'DROP INDEX `posts_old_index`',
            'ALTER TABLE `posts` RENAME COLUMN `body` TO `content`',
            'ALTER TABLE `posts` ADD COLUMN `subtitle` VARCHAR(255) NULL',
        ], $table->toAlterStatements());

        $foreign = new Blueprint('posts', 'sqlite');
        $foreign->dropForeign('posts_user_id_foreign');
        $this->assertThrows(\LogicException::class, fn() => $foreign->toAlterStatements());
    }

    public function testMysqlOutputIsUnchanged(): void
    {
        $table = new Blueprint('posts');
        $table->id();
        $this->assertSame([$table->toCreateSql()], $table->toCreateStatements());
    }

    public function testTheSqlRunsOnSqlite(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->assertTrue(true);
            return; // pdo_sqlite is not enabled in this PHP
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $users = new Blueprint('users', 'sqlite');
        $users->id();
        $users->string('name');
        foreach ([...$users->toCreateStatements(), ...$this->posts()->toCreateStatements()] as $sql) {
            $pdo->exec($sql);
        }

        $pdo->exec("INSERT INTO users (name) VALUES ('Ana')");
        $pdo->exec("INSERT INTO posts (user_id, slug) VALUES (1, 'hello')");
        $row = $pdo->query('SELECT * FROM posts')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('news', $row['kind']);
        $this->assertSame(date('Y-m-d'), substr($row['created_at'], 0, 10), 'defaults use PHP\'s timezone');

        // enum CHECK and unique index
        $this->assertThrows(\PDOException::class, fn() => $pdo->exec("INSERT INTO posts (slug, kind) VALUES ('x', 'other')"));
        $this->assertThrows(\PDOException::class, fn() => $pdo->exec("INSERT INTO posts (slug) VALUES ('hello')"));

        // updated_at refreshes on change, unless the update sets it itself
        $pdo->exec("UPDATE posts SET updated_at = '2000-01-01 00:00:00'");
        $pdo->exec("UPDATE posts SET published = 1");
        $this->assertSame(date('Y-m-d'), substr($pdo->query('SELECT updated_at FROM posts')->fetchColumn(), 0, 10));

        // the foreign key cascades
        $pdo->exec('DELETE FROM users');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());
    }
}
