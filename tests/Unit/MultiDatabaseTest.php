<?php

namespace Tests\Unit;

use PDO;
use System\Database\Connection;
use System\Database\DB;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class TenantRecord extends Model
{
    protected static $table = 'tenant_records';
    protected static ?string $connection = 'tenant_test';
    protected array $fillable = ['title', 'notes'];
}

class TenantItem extends Model
{
    protected static $table = 'tenant_items';
    protected static ?string $connection = 'tenant_test';
    protected array $fillable = ['tenant_record_id', 'item_name'];

    public function record()
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_record_id');
    }
}

class MultiDatabaseTest extends TestCase
{
    protected ?string $tenantDbPath = null;

    protected function isSqlite(): bool
    {
        return in_array('sqlite', PDO::getAvailableDrivers(), true);
    }

    public function setUp(): void
    {
        if ($this->isSqlite()) {
            $this->tenantDbPath = BASE_PATH . '/storage/testing/tenant_test.sqlite';
            if (file_exists($this->tenantDbPath)) {
                @unlink($this->tenantDbPath);
            }
            $config = [
                'driver'   => 'sqlite',
                'database' => $this->tenantDbPath,
            ];
        } else {
            $default = config('database.default');
            $config = config("database.connections.$default");
        }

        Connection::addConnection('tenant_test', $config);

        Schema::connection('tenant_test')->dropIfExists('tenant_items');
        Schema::connection('tenant_test')->dropIfExists('tenant_records');

        Schema::connection('tenant_test')->create('tenant_records', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant_test')->create('tenant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_record_id');
            $table->string('item_name');
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        try {
            Schema::connection('tenant_test')->dropIfExists('tenant_items');
            Schema::connection('tenant_test')->dropIfExists('tenant_records');
        } catch (\Throwable) {
        }

        Connection::reset('tenant_test');
        Connection::removeConnection('tenant_test');

        if ($this->tenantDbPath && file_exists($this->tenantDbPath)) {
            @unlink($this->tenantDbPath);
        }
    }

    public function testSimultaneousQueryingAcrossMultipleDatabases(): void
    {
        // 1. Insert into tenant_test connection
        DB::connection('tenant_test')->table('tenant_records')->insert([
            'title'      => 'Tenant Company A',
            'notes'      => 'Isolated Tenant Database',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Query both connections in the same execution context
        $tenantRows = DB::connection('tenant_test')->table('tenant_records')->get();
        $defaultUsers = DB::table('users')->get();

        $this->assertCount(1, $tenantRows);
        $this->assertSame('Tenant Company A', $tenantRows[0]->title);
        $this->assertTrue(is_array($defaultUsers));

        $this->assertTrue(Schema::connection('tenant_test')->hasTable('tenant_records'));
    }

    public function testIsolatedTransactionsPerConnection(): void
    {
        $tenantConn = DB::connection('tenant_test');

        $tenantConn->beginTransaction();
        $tenantConn->table('tenant_records')->insert([
            'title'      => 'Pending Rollback',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->assertSame(1, $tenantConn->transactionLevel());
        $this->assertSame(0, DB::transactionLevel(), 'Default connection transaction level remains untouched');

        $tenantConn->rollBack();
        $this->assertSame(0, $tenantConn->transactionLevel());
        $this->assertCount(0, $tenantConn->table('tenant_records')->get());
    }

    public function testModelStaticConnectionRoutingAndDynamicOn(): void
    {
        // Model with protected static ?string $connection = 'tenant_test';
        $record = TenantRecord::create([
            'title' => 'SaaS Subscription',
            'notes' => 'Created via Model with static $connection',
        ]);

        $this->assertTrue($record->id > 0);
        $this->assertSame('tenant_test', $record->getConnection());

        $found = TenantRecord::find($record->id);
        $this->assertNotNull($found);
        $this->assertSame('SaaS Subscription', $found->title);

        // Update the model on the tenant database
        $found->title = 'SaaS Subscription Pro';
        $found->save();

        $refreshed = TenantRecord::find($record->id);
        $this->assertSame('SaaS Subscription Pro', $refreshed->title);

        // Delete from tenant database
        $refreshed->delete();
        $this->assertNull(TenantRecord::find($record->id));
    }

    public function testModelDynamicOnMethod(): void
    {
        // Create an item using TenantItem::on('tenant_test')
        $item = TenantItem::on('tenant_test')->create([
            'tenant_record_id' => 42,
            'item_name'        => 'Custom Dynamic Item',
        ]);

        $this->assertTrue($item->id > 0);
        $this->assertSame('tenant_test', $item->getConnection());

        $fetched = TenantItem::on('tenant_test')->where('tenant_record_id', 42)->first();
        $this->assertNotNull($fetched);
        $this->assertSame('Custom Dynamic Item', $fetched->item_name);
    }

    public function testRelationshipsOnTargetConnection(): void
    {
        $record = TenantRecord::create([
            'title' => 'Master Record',
            'notes' => 'Parent of relation',
        ]);

        $item = TenantItem::create([
            'tenant_record_id' => $record->id,
            'item_name'        => 'Child Item 1',
        ]);

        $loadedRecord = $item->record;
        $this->assertNotNull($loadedRecord);
        $this->assertSame('Master Record', $loadedRecord->title);
    }

    public function testRuntimeDynamicConnectionRegistrationAndRemoval(): void
    {
        if ($this->isSqlite()) {
            $dynamicDbPath = BASE_PATH . '/storage/testing/dynamic_test.sqlite';
            if (file_exists($dynamicDbPath)) {
                @unlink($dynamicDbPath);
            }
            $config = [
                'driver'   => 'sqlite',
                'database' => $dynamicDbPath,
            ];
        } else {
            $default = config('database.default');
            $config = config("database.connections.$default");
        }

        $this->assertFalse(Connection::hasConnection('dynamic_test'));

        Connection::addConnection('dynamic_test', $config);

        $this->assertTrue(Connection::hasConnection('dynamic_test'));
        $this->assertSame($config['driver'], Connection::driver('dynamic_test'));

        Schema::connection('dynamic_test')->dropIfExists('metrics_test');
        Schema::connection('dynamic_test')->create('metrics_test', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name');
            $table->string('metric_value');
        });

        DB::connection('dynamic_test')->table('metrics_test')->insert(['metric_name' => 'cpu', 'metric_value' => '99%']);
        $row = DB::connection('dynamic_test')->table('metrics_test')->first();
        $this->assertSame('99%', $row->metric_value);

        Schema::connection('dynamic_test')->dropIfExists('metrics_test');

        Connection::removeConnection('dynamic_test');
        $this->assertFalse(Connection::hasConnection('dynamic_test'));

        if (!empty($dynamicDbPath) && file_exists($dynamicDbPath)) {
            @unlink($dynamicDbPath);
        }
    }

    public function testMigratorOnNamedConnection(): void
    {
        $dir = BASE_PATH . '/storage/testing/migrations_' . uniqid();
        mkdir($dir, 0777, true);

        file_put_contents("$dir/2099_01_01_000001_create_tenant_posts_table.php", '<?php
use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("tenant_posts", function (Blueprint $table) {
            $table->id();
            $table->string("headline");
        });
    }
    public function down(): void {
        Schema::dropIfExists("tenant_posts");
    }
};');

        try {
            $migrator = new \System\Database\Migrator($dir, 'tenant_test');
            $migrated = $migrator->migrate();

            $this->assertSame(['2099_01_01_000001_create_tenant_posts_table'], $migrated);
            $this->assertTrue(Schema::connection('tenant_test')->hasTable('tenant_posts'));
            $this->assertTrue(Schema::connection('tenant_test')->hasColumn('tenant_posts', 'headline'));

            // Test rollback on target connection
            $rolled = $migrator->rollback();
            $this->assertSame(['2099_01_01_000001_create_tenant_posts_table'], $rolled);
            $this->assertFalse(Schema::connection('tenant_test')->hasTable('tenant_posts'));
        } finally {
            array_map('unlink', glob("$dir/*.php"));
            @rmdir($dir);
        }
    }
}
