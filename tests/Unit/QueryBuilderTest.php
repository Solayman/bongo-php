<?php

namespace Tests\Unit;

use App\Models\User;
use System\Database\DB;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class QbItem extends Model
{
    protected static $table = 'qb_items';
}

class QueryBuilderTest extends TestCase
{
    public function setUp(): void
    {
        Schema::dropIfExists('qb_items');
        Schema::dropIfExists('qb_groups');

        Schema::create('qb_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('qb_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->references('qb_groups')->onDelete('set null');
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('status', ['active', 'draft'])->default('active');
        });

        $a = DB::table('qb_groups')->insertGetId(['name' => 'Group A']);
        $b = DB::table('qb_groups')->insertGetId(['name' => 'Group B']);
        for ($i = 1; $i <= 20; $i++) {
            DB::table('qb_items')->insert([
                'group_id' => $i % 2 ? $a : $b,
                'name'     => "Item $i",
                'price'    => $i * 1.5,
                'status'   => $i % 5 === 0 ? 'draft' : 'active',
            ]);
        }
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('qb_items');
        Schema::dropIfExists('qb_groups');
    }

    public function testBuildsSelectSql(): void
    {
        $sql = DB::table('qb_items')
            ->select('qb_items.id', 'qb_groups.name AS grp')
            ->leftJoin('qb_groups', 'qb_groups.id', '=', 'qb_items.group_id')
            ->where('price', '>', 10)
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('price', 'desc')
            ->limit(5)
            ->offset(10)
            ->toSql();

        $this->assertSame(
            'SELECT `qb_items`.`id`, `qb_groups`.`name` AS `grp` FROM `qb_items` LEFT JOIN `qb_groups` ON `qb_groups`.`id` = `qb_items`.`group_id`'
            . ' WHERE `price` > ? AND `status` IN (?,?) ORDER BY `price` DESC LIMIT 5 OFFSET 10',
            $sql
        );
    }

    public function testRejectsBadOperatorAndDirection(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('qb_items')->where('id', '; DROP', 1));
        $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('qb_items')->orderBy('id', 'DESC; DROP'));
    }

    public function testCrudRoundTrip(): void
    {
        $id = DB::table('qb_items')->insertGetId(['name' => 'Temp', 'price' => 3]);
        $this->assertSame('Temp', DB::table('qb_items')->where('id', $id)->first()->name);

        DB::table('qb_items')->where('id', $id)->update(['name' => 'Temp 2']);
        $this->assertSame('Temp 2', DB::table('qb_items')->where('id', $id)->first()->name);

        DB::table('qb_items')->where('id', $id)->delete();
        $this->assertNull(DB::table('qb_items')->where('id', $id)->first());
    }

    public function testJoinsAndFilters(): void
    {
        $rows = DB::table('qb_items')
            ->select('qb_items.name', 'qb_groups.name AS grp')
            ->join('qb_groups', 'qb_groups.id', '=', 'qb_items.group_id')
            ->where('qb_groups.name', 'Group B')
            ->whereRaw('qb_items.price >= ? OR qb_items.name LIKE ?', [27, 'Item 2'])
            ->orderBy('qb_items.id')
            ->get();

        $this->assertSame(['Item 2', 'Item 18', 'Item 20'], array_column($rows, 'name'));
        $this->assertSame('Group B', $rows[0]->grp);
        $this->assertCount(0, DB::table('qb_items')->whereNull('name')->get());
        $this->assertCount(20, DB::table('qb_items')->whereNotNull('group_id')->get());
        $this->assertCount(0, DB::table('qb_items')->whereIn('id', [])->get());
    }

    public function testRefusesToUpdateOrDeleteEveryRow(): void
    {
        $this->assertThrows(\LogicException::class, fn() => DB::table('qb_items')->update(['price' => 0]));
        $this->assertThrows(\LogicException::class, fn() => DB::table('qb_items')->delete(false));
        $this->assertTrue(DB::table('qb_items')->update(['price' => 1], true));
        $this->assertEquals(20, DB::table('qb_items')->sum('price'));
    }

    public function testDeleteUnconstrainedAndById(): void
    {
        $id = DB::table('qb_items')->insertGetId(['name' => 'To Delete', 'price' => 10]);
        $this->assertTrue(DB::table('qb_items')->delete($id));
        $this->assertNull(DB::table('qb_items')->where('id', $id)->first());

        $this->assertTrue(DB::table('qb_items')->delete());
        $this->assertSame(0, DB::table('qb_items')->count());
    }

    public function testAggregatesAndPaginate(): void
    {
        $this->assertSame(20, DB::table('qb_items')->count());
        $this->assertSame(4, DB::table('qb_items')->where('status', 'draft')->count());
        $this->assertEquals(315, DB::table('qb_items')->sum('price')); // 1.5 * (1 + ... + 20)

        $page = DB::table('qb_items')->orderBy('id')->paginate(6, 4);
        $this->assertSame(20, $page['total']);
        $this->assertSame(4, $page['last_page']);
        $this->assertSame(4, $page['current_page']);
        $this->assertCount(2, $page['data']);

        $this->assertSame(4, DB::table('qb_items')->paginate(6, 99)['current_page'], 'Out-of-range pages clamp to the last page');
        $this->assertTrue(is_array(DB::table('qb_items')->asArray()->first()));
    }

    public function testCountWithJoinsReturnsDistinctParentCount(): void
    {
        // 2 groups have 20 items in total (10 each)
        // With joins, count() on qb_groups should count distinct groups (2), not inflated child rows (20)
        $count = DB::table('qb_groups')
            ->leftJoin('qb_items', 'qb_items.group_id', '=', 'qb_groups.id')
            ->count();

        $this->assertSame(2, $count);

        // Explicit distinct count
        $distinctCount = DB::table('qb_groups')
            ->distinct()
            ->leftJoin('qb_items', 'qb_items.group_id', '=', 'qb_groups.id')
            ->count('qb_groups.id');

        $this->assertSame(2, $distinctCount);
    }

    public function testModelHelpers(): void
    {
        $this->assertSame('admin@example.com', User::find(1)->email);
        $this->assertSame('Admin', User::where('email', 'admin@example.com')->first()->name);
        $this->assertCount(20, QbItem::all());
        $this->assertCount(4, QbItem::where('status', 'draft')->get());
        $this->assertCount(2, QbItem::whereIn('id', [1, 2])->get());
        $this->assertSame('Via model', QbItem::create(['name' => 'Via model'])->name);
        $this->assertSame('users', User::tableName());
    }

    public function testInsertsManyRowsInOneStatement(): void
    {
        DB::enableQueryLog();
        DB::table('qb_groups')->insert([['name' => 'Bulk 1'], ['name' => 'Bulk 2'], ['name' => 'Bulk 3']]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'one INSERT for three rows');
        $this->assertSame(3, DB::table('qb_groups')->where('name', 'LIKE', 'Bulk %')->count());
        $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('qb_groups')->insert([['name' => 'A'], ['id' => 9]]));
    }

    public function testColumnNamesFromUserInputCannotInjectSql(): void
    {
        $qb = fn() => DB::table('qb_items');
        foreach (['id; DROP TABLE qb_items', 'name OR 1=1 --', '(SELECT 1)', 'id DESC, (SELECT', ''] as $bad) {
            $this->assertThrows(\InvalidArgumentException::class, fn() => $qb()->orderBy($bad)->get(), "orderBy [$bad]");
            $this->assertThrows(\InvalidArgumentException::class, fn() => $qb()->where($bad, 1)->get(), "where [$bad]");
            $this->assertThrows(\InvalidArgumentException::class, fn() => $qb()->whereIn($bad, [1])->get(), "whereIn [$bad]");
        }
        $this->assertThrows(\InvalidArgumentException::class, fn() => $qb()->insert(['name = (SELECT 1), price' => 'x']));
        $this->assertThrows(\InvalidArgumentException::class, fn() => $qb()->where('id', 1)->update(['price = 0, name' => 'x']));

        // Plain and table-qualified names keep working
        $this->assertSame(20, $qb()->orderBy('qb_items.name')->where('qb_items.price', '>=', 0)->count());
    }

    public function testRawClausesAndBindings(): void
    {
        // selectRaw with binding and addSelect
        $row = DB::table('qb_items')
            ->selectRaw('price * ? AS double_price', [2])
            ->addSelect('name')
            ->where('name', 'Item 1')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Item 1', $row->name);
        $this->assertEquals(3.0, (float) $row->double_price);

        // orderByRaw with bindings/custom expression
        $items = DB::table('qb_items')
            ->whereIn('id', [1, 2, 3])
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [2])
            ->get();
        $this->assertSame('Item 2', $items[0]->name);

        // groupByRaw
        $grouped = DB::table('qb_items')
            ->select('status')
            ->selectRaw('COUNT(*) AS cnt')
            ->groupByRaw('status')
            ->pluck('cnt', 'status');

        $this->assertSame(16, (int) $grouped['active']);
        $this->assertSame(4, (int) $grouped['draft']);
    }
}
