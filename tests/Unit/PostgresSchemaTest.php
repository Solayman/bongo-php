<?php

namespace Tests\Unit;

use PDO;
use System\Database\Connection;
use System\Database\QueryBuilder;
use System\Database\Schema\Blueprint;
use System\Testing\TestCase;

/**
 * Migration and Query generation compiled for PostgreSQL.
 * DDL is checked against PostgreSQL specifications;
 * live queries run if the pdo_pgsql extension and server are present.
 */
class PostgresSchemaTest extends TestCase
{
    protected function posts(): Blueprint
    {
        $table = new Blueprint('posts', 'pgsql');
        $table->id();
        $table->foreignId('user_id')->nullable()->references('users')->onDelete('cascade');
        $table->string('slug')->unique()->comment('URL slug identifier');
        $table->boolean('published')->default(false);
        $table->enum('kind', ['news', "it's"])->default('news');
        $table->json('meta')->nullable();
        $table->index(['published', 'kind']);
        $table->timestamps();
        return $table;
    }

    public function testCompilesCreateTableForPostgres(): void
    {
        $statements = $this->posts()->toCreateStatements();
        $sql = implode(";\n", $statements);

        $this->assertContains('"id" BIGSERIAL PRIMARY KEY', $sql);
        $this->assertContains('"user_id" BIGINT NULL', $sql);
        $this->assertContains('"slug" VARCHAR(255) NOT NULL', $sql);
        $this->assertContains('"published" BOOLEAN NOT NULL DEFAULT FALSE', $sql);
        $this->assertContains('"kind" VARCHAR(50) NOT NULL DEFAULT \'news\' CHECK ("kind" IN (\'news\', \'it\'\'s\'))', $sql);
        $this->assertContains('"meta" JSONB NULL', $sql);
        $this->assertContains('CREATE UNIQUE INDEX IF NOT EXISTS "posts_slug_unique" ON "posts" ("slug")', $sql);
        $this->assertContains('CREATE INDEX IF NOT EXISTS "posts_published_kind_index" ON "posts" ("published", "kind")', $sql);
        $this->assertContains('CONSTRAINT "posts_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE', $sql);
        $this->assertContains('COMMENT ON COLUMN "posts"."slug" IS \'URL slug identifier\'', $sql);
        $this->assertContains('CREATE OR REPLACE FUNCTION "bongo_posts_updated_at_trigger"()', $sql);
        $this->assertContains('CREATE TRIGGER "trg_posts_updated_at_update"', $sql);

        // Disallowed MySQL/SQLite dialects in PostgreSQL
        $this->assertNotContains('AUTO_INCREMENT', $sql);
        $this->assertNotContains('UNSIGNED', $sql);
        $this->assertNotContains('ENGINE=InnoDB', $sql);
        $this->assertNotContains('`', $sql);
    }

    public function testCompilesCompositePrimaryKeyForPostgres(): void
    {
        $table = new Blueprint('role_has_permissions', 'pgsql');
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('permission_id');
        $table->primary(['role_id', 'permission_id']);

        $statements = $table->toCreateStatements();
        $sql = implode(";\n", $statements);
        $this->assertContains('PRIMARY KEY ("role_id", "permission_id")', $sql);
    }

    public function testAlterStatementsForPostgres(): void
    {
        $table = new Blueprint('posts', 'pgsql');
        $table->string('subtitle')->nullable();
        $table->renameColumn('body', 'content');
        $table->dropColumn('legacy');
        $table->dropIndex('posts_old_index');
        $table->dropForeign('posts_user_id_foreign');

        $statements = $table->toAlterStatements();

        $this->assertSame([
            'ALTER TABLE "posts" DROP COLUMN "legacy"',
            'DROP INDEX IF EXISTS "posts_old_index"',
            'ALTER TABLE "posts" DROP CONSTRAINT IF EXISTS "posts_user_id_foreign"',
            'ALTER TABLE "posts" RENAME COLUMN "body" TO "content"',
            'ALTER TABLE "posts" ADD COLUMN "subtitle" VARCHAR(255) NULL',
        ], $statements);

        $primaryTable = new Blueprint('posts', 'pgsql');
        $primaryTable->id('new_id');
        $this->assertThrows(\LogicException::class, fn() => $primaryTable->toAlterStatements());
    }

    public function testAlterColumnChangeForPostgres(): void
    {
        $table = new Blueprint('posts', 'pgsql');
        $table->string('email', 150)->nullable()->change();
        $table->dropForeign(['user_id']);

        $statements = $table->toAlterStatements();
        $this->assertSame('ALTER TABLE "posts" DROP CONSTRAINT IF EXISTS "posts_user_id_foreign"', $statements[0]);
        $this->assertSame('ALTER TABLE "posts" ALTER COLUMN "email" TYPE VARCHAR(150)', $statements[1]);
        $this->assertSame('ALTER TABLE "posts" ALTER COLUMN "email" DROP NOT NULL', $statements[2]);
    }

    public function testIdentifierQuotingUsesDoubleQuotes(): void
    {
        $this->assertSame('"users"', Blueprint::wrap('users', 'pgsql'));
        $this->assertSame('"user""name"', Blueprint::wrap('user"name', 'pgsql'));
    }

    public function testPostgresQueryBuilderSqlGeneration(): void
    {
        $qb = (new QueryBuilder('users'))->setConnection('mysql');
        $qb->where('id', 1);
        $this->assertSame('SELECT * FROM `users` WHERE `id` = ?', $qb->toSql());
        $qbPg = (new QueryBuilder('users'))->setConnection('pgsql');
        $qbPg->where('id', 1);
        $this->assertSame('SELECT * FROM "users" WHERE "id" = ?', $qbPg->toSql());
    }
}
