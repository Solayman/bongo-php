<?php
namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use System\Database\DB;
use System\Testing\TestCase;

class OrmTest extends TestCase
{
    public function tearDown(): void
    {
        DB::table('categories')->where('slug', 'LIKE', 'orm-%')->delete();
        DB::table('products')->where('sku', 'LIKE', 'ORM-%')->delete();
        DB::disableQueryLog();
    }

    // --- Models ----------------------------------------------------------

    public function testFindReturnsModelWithCasts(): void
    {
        $product = Product::find(1);
        $this->assertTrue($product instanceof Product);
        $this->assertTrue($product->exists);
        $this->assertSame(39.99, $product->price, 'decimal cast to float');
        $this->assertTrue(is_int($product->stock));
        $this->assertNull(Product::find(999999));
        $this->assertThrows(\System\Http\HttpException::class, fn() => Product::findOrFail(999999));
    }

    public function testCreateSaveOnlyDirtyColumnsAndDelete(): void
    {
        $category = Category::create(['name' => 'ORM Test', 'slug' => 'orm-test']);
        $this->assertTrue($category->id > 0);
        $this->assertNotSame(null, $category->created_at, 'database defaults are loaded after insert');
        $this->assertFalse($category->isDirty());

        $category->name = 'ORM Renamed';
        $this->assertTrue($category->isDirty('name'));
        $this->assertFalse($category->isDirty('slug'));

        DB::enableQueryLog();
        $category->save();
        $update = DB::getQueryLog()[0];
        $this->assertSame('UPDATE categories SET name = ? WHERE id = ?', $update['sql'], 'only the changed column is written');
        $this->assertSame('ORM Renamed', Category::find($category->id)->name);
        $this->assertSame('orm-test', $category->getOriginal('slug'));

        DB::flushQueryLog();
        $category->save();
        $this->assertCount(0, DB::getQueryLog(), 'saving an unchanged model runs no query');

        $this->assertTrue($category->delete());
        $this->assertNull(Category::find($category->id));
        $this->assertFalse($category->exists);
    }

    public function testMassAssignmentProtection(): void
    {
        $user = new User(['name' => 'X', 'id' => 999, 'is_admin' => true, 'role' => 'customer']);
        $this->assertNull($user->id, 'id is guarded');
        $this->assertNull($user->is_admin, 'not in $fillable');
        $this->assertSame('customer', $user->role);
    }

    public function testHiddenColumnsNeverSerialized(): void
    {
        $admin = User::find(1);
        $this->assertTrue(str_starts_with($admin->password, '$'), 'still readable in code');
        $this->assertArrayHasKey('email', $admin->toArray());
        $this->assertFalse(array_key_exists('password', $admin->toArray()));
        $this->assertNotContains('password', json_encode($admin));
    }

    public function testUpdateAndDestroy(): void
    {
        $p = Product::create(['name' => 'ORM Mug', 'sku' => 'ORM-1', 'price' => 3.5, 'stock' => 2, 'category_id' => 1]);
        $p->update(['price' => 4.25]);
        $this->assertSame(4.25, Product::find($p->id)->price);
        $this->assertSame(1, Product::destroy([$p->id, 999999]));
    }

    // --- Relations ---------------------------------------------------------

    public function testRelationsAsMethodsAndProperties(): void
    {
        $order = Order::find(1);
        $items = $order->items;
        $this->assertTrue(count($items) >= 1);
        $this->assertTrue($items[0] instanceof OrderItem);
        $this->assertSame($order->id, $items[0]->order->id, 'belongsTo back to the order');
        $this->assertSame(User::class, get_class($order->user));

        $bigItems = $order->items()->where('quantity', '>=', 1)->orderBy('id', 'DESC')->get();
        $this->assertCount(count($items), $bigItems, 'a relation can be queried further');

        $this->assertCount(4, Category::find(2)->products, 'Fashion has 4 products');
        $this->assertSame('Electronics', Product::find(1)->category->name);
    }

    public function testHasManyCreateFillsForeignKey(): void
    {
        $category = Category::create(['name' => 'ORM Parent', 'slug' => 'orm-parent']);
        $product = $category->products()->create(['name' => 'ORM Child', 'sku' => 'ORM-2', 'price' => 1, 'stock' => 1]);
        $this->assertSame($category->id, $product->category_id);
        $category->delete();
    }

    public function testEagerLoadingUsesOneQueryPerRelation(): void
    {
        DB::enableQueryLog();
        $orders = Order::with('items', 'user')->orderBy('id')->limit(10)->get();
        foreach ($orders as $order) {
            $order->items;      // already loaded
            $order->user->name; // already loaded
        }
        $this->assertCount(10, $orders);
        $this->assertCount(3, DB::getQueryLog(), '1 query for orders + 1 for all items + 1 for all users');

        DB::flushQueryLog();
        foreach (Order::query()->orderBy('id')->limit(10)->get() as $order) {
            $order->items;
        }
        $this->assertCount(11, DB::getQueryLog(), 'without with(): 1 + one query per order');

        $json = json_decode(json_encode($orders[0]), true);
        $this->assertArrayHasKey('items', $json, 'loaded relations are serialized');
        $this->assertFalse(array_key_exists('password', $json['user']));
    }

    // --- Query builder ------------------------------------------------------

    public function testOrWhereAndGroupedConditions(): void
    {
        $sql = DB::table('products')->where('status', 'active')
            ->where(fn($q) => $q->where('price', '<', 10)->orWhere('stock', '<', 5))
            ->orWhereNull('category_id')->toSql();
        $this->assertSame('SELECT * FROM products WHERE status = ? AND (price < ? OR stock < ?) OR category_id IS NULL', $sql);

        $rows = DB::table('products')->where('status', 'active')
            ->where(fn($q) => $q->where('price', '<', 10)->orWhere('sku', 'BG-1000'))->pluck('sku');
        $this->assertTrue(in_array('BG-1000', $rows, true));
        $this->assertTrue(in_array('BG-1019', $rows, true), 'Water Bottle costs 9.99');
    }

    public function testGroupByHavingAndAggregates(): void
    {
        $perCategory = DB::table('products')->select('category_id', 'COUNT(*) AS n')->groupBy('category_id')
            ->having('COUNT(*)', '>=', 4)->orderBy('category_id')->pluck('n', 'category_id');
        $this->assertEquals([1 => 5, 2 => 4, 3 => 4, 5 => 4], $perCategory);

        $this->assertSame(4, DB::table('products')->groupBy('category_id')->having('COUNT(*)', '>=', 4)->count(), 'count of groups');
        $this->assertEquals(129.0, (float) DB::table('products')->max('price'));
        $this->assertEquals(9.99, (float) DB::table('products')->min('price'));
        $this->assertTrue(DB::table('products')->avg('price') > 9.99);
    }

    public function testPluckValueExistsDistinctBetween(): void
    {
        $this->assertSame('Wireless Earbuds', DB::table('products')->where('id', 1)->value('name'));
        $this->assertSame([1 => 'BG-1000', 2 => 'BG-1001'], DB::table('products')->whereIn('id', [1, 2])->pluck('sku', 'id'));
        $this->assertTrue(DB::table('users')->where('email', 'admin@example.com')->exists());
        $this->assertTrue(DB::table('users')->where('email', 'nobody@nowhere')->doesntExist());
        $this->assertCount(5, DB::table('products')->distinct()->select('category_id')->get());
        $this->assertCount(3, DB::table('products')->whereBetween('id', 2, 4)->get());
        $this->assertCount(18, DB::table('products')->whereNotIn('id', [1, 2])->get());
    }

    public function testIncrementDecrementAndChunk(): void
    {
        $before = (int) DB::table('products')->where('id', 3)->value('stock');
        DB::table('products')->where('id', 3)->increment('stock', 5);
        DB::table('products')->where('id', 3)->decrement('stock', 2);
        $this->assertSame($before + 3, (int) DB::table('products')->where('id', 3)->value('stock'));
        DB::table('products')->where('id', 3)->update(['stock' => $before]);
        $this->assertThrows(\LogicException::class, fn() => DB::table('products')->increment('stock'));

        $seen = 0;
        $batches = 0;
        DB::table('products')->orderBy('id')->chunk(6, function ($rows) use (&$seen, &$batches) {
            $seen += count($rows);
            $batches++;
        });
        $this->assertSame([20, 4], [$seen, $batches]);

        $stopped = 0;
        DB::table('products')->orderBy('id')->chunk(5, function () use (&$stopped) { $stopped++; return false; });
        $this->assertSame(1, $stopped, 'returning false stops chunking');
    }

    // --- Transactions ---------------------------------------------------------

    public function testTransactionCommitsAndRollsBack(): void
    {
        DB::transaction(fn() => DB::table('categories')->insert(['name' => 'ORM Commit', 'slug' => 'orm-commit']));
        $this->assertTrue(DB::table('categories')->where('slug', 'orm-commit')->exists());

        try {
            DB::transaction(function () {
                DB::table('categories')->insert(['name' => 'ORM Rollback', 'slug' => 'orm-rollback']);
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertFalse(DB::table('categories')->where('slug', 'orm-rollback')->exists(), 'rolled back');
        $this->assertSame(0, DB::transactionLevel());
    }

    public function testNestedTransactionsUseSavepoints(): void
    {
        DB::transaction(function () {
            DB::table('categories')->insert(['name' => 'ORM Outer', 'slug' => 'orm-outer']);
            try {
                DB::transaction(function () {
                    DB::table('categories')->insert(['name' => 'ORM Inner', 'slug' => 'orm-inner']);
                    throw new \RuntimeException('inner fails');
                });
            } catch (\RuntimeException $e) {
            }
        });

        $this->assertTrue(DB::table('categories')->where('slug', 'orm-outer')->exists(), 'outer work is kept');
        $this->assertFalse(DB::table('categories')->where('slug', 'orm-inner')->exists(), 'only the inner part is undone');
    }

    public function testQueryLogRecordsSqlBindingsAndTime(): void
    {
        DB::enableQueryLog();
        DB::table('users')->where('email', 'admin@example.com')->first();
        $log = DB::getQueryLog();
        $this->assertSame('SELECT * FROM users WHERE email = ? LIMIT 1', $log[0]['sql']);
        $this->assertSame(['admin@example.com'], $log[0]['bindings']);
        $this->assertTrue(is_float($log[0]['ms']) || is_int($log[0]['ms']));
    }
}
