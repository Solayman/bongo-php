<?php

namespace Tests\Unit\OrmProbe {
    use App\Models\User;
    use System\Database\Model;

    class OrmCategory extends Model
    {
        protected static $table = 'orm_categories';
        protected array $fillable = ['name', 'slug'];

        public function products()
        {
            return $this->hasMany(OrmProduct::class, 'category_id');
        }
    }

    class OrmProduct extends Model
    {
        protected static $table = 'orm_products';
        protected array $fillable = ['name', 'sku', 'price', 'stock', 'status', 'category_id'];
        protected array $casts = ['id' => 'int', 'price' => 'float', 'stock' => 'int'];

        public function category()
        {
            return $this->belongsTo(OrmCategory::class, 'category_id');
        }
    }

    class OrmOrder extends Model
    {
        protected static $table = 'orm_orders';
        protected array $fillable = ['user_id', 'order_number', 'total', 'status'];

        public function items()
        {
            return $this->hasMany(OrmOrderItem::class, 'order_id');
        }

        public function user()
        {
            return $this->belongsTo(User::class, 'user_id');
        }
    }

    class OrmOrderItem extends Model
    {
        protected static $table = 'orm_order_items';
        protected array $fillable = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_price'];
        protected array $casts = ['id' => 'int', 'quantity' => 'int', 'unit_price' => 'float'];

        public function order()
        {
            return $this->belongsTo(OrmOrder::class, 'order_id');
        }
    }
}

namespace Tests\Unit {

    use App\Models\User;
    use System\Database\DB;
    use System\Database\Schema\Blueprint;
    use System\Database\Schema\Schema;
    use System\Testing\TestCase;
    use Tests\Unit\OrmProbe\OrmCategory;
    use Tests\Unit\OrmProbe\OrmOrder;
    use Tests\Unit\OrmProbe\OrmOrderItem;
    use Tests\Unit\OrmProbe\OrmProduct;

    class OrmTest extends TestCase
    {
        public function setUp(): void
        {
            $this->tearDown();

            Schema::create('orm_categories', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug');
                $t->timestamps();
            });

            Schema::create('orm_products', function (Blueprint $t) {
                $t->id();
                $t->integer('category_id')->nullable();
                $t->string('name');
                $t->string('sku')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->integer('stock')->default(0);
                $t->string('status')->default('active');
                $t->timestamps();
            });

            Schema::create('orm_orders', function (Blueprint $t) {
                $t->id();
                $t->integer('user_id')->nullable();
                $t->string('order_number');
                $t->decimal('total', 10, 2)->default(0);
                $t->string('status')->default('pending');
                $t->timestamps();
            });

            Schema::create('orm_order_items', function (Blueprint $t) {
                $t->id();
                $t->integer('order_id');
                $t->integer('product_id')->nullable();
                $t->string('product_name');
                $t->integer('quantity')->default(1);
                $t->decimal('unit_price', 10, 2)->default(0);
                $t->timestamps();
            });

            // Seed test categories
            $now = date('Y-m-d H:i:s');
            $c1 = DB::table('orm_categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics', 'created_at' => $now, 'updated_at' => $now]);
            $c2 = DB::table('orm_categories')->insertGetId(['name' => 'Fashion', 'slug' => 'fashion', 'created_at' => $now, 'updated_at' => $now]);
            $c3 = DB::table('orm_categories')->insertGetId(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'created_at' => $now, 'updated_at' => $now]);
            $c4 = DB::table('orm_categories')->insertGetId(['name' => 'Books', 'slug' => 'books', 'created_at' => $now, 'updated_at' => $now]);
            $c5 = DB::table('orm_categories')->insertGetId(['name' => 'Sports', 'slug' => 'sports', 'created_at' => $now, 'updated_at' => $now]);

            // Seed test products: 20 total
            // Category 1: 5 products
            DB::table('orm_products')->insert(['category_id' => $c1, 'name' => 'Wireless Earbuds', 'sku' => 'BG-1000', 'price' => 39.99, 'stock' => 50, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c1, 'name' => 'Bluetooth Speaker', 'sku' => 'BG-1001', 'price' => 54.50, 'stock' => 30, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c1, 'name' => 'USB-C Charger', 'sku' => 'BG-1002', 'price' => 29.00, 'stock' => 10, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c1, 'name' => 'Smart Watch', 'sku' => 'BG-1003', 'price' => 129.00, 'stock' => 15, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c1, 'name' => 'Power Bank', 'sku' => 'BG-1004', 'price' => 34.99, 'stock' => 25, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

            // Category 2: 4 products
            DB::table('orm_products')->insert(['category_id' => $c2, 'name' => 'T-Shirt', 'sku' => 'BG-1005', 'price' => 12.50, 'stock' => 40, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c2, 'name' => 'Jacket', 'sku' => 'BG-1006', 'price' => 59.00, 'stock' => 20, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c2, 'name' => 'Wallet', 'sku' => 'BG-1007', 'price' => 24.99, 'stock' => 35, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c2, 'name' => 'Sneakers', 'sku' => 'BG-1008', 'price' => 79.90, 'stock' => 18, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

            // Category 3: 4 products
            DB::table('orm_products')->insert(['category_id' => $c3, 'name' => 'Frying Pan', 'sku' => 'BG-1009', 'price' => 27.50, 'stock' => 22, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c3, 'name' => 'Kettle', 'sku' => 'BG-1010', 'price' => 32.00, 'stock' => 14, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c3, 'name' => 'Mug Set', 'sku' => 'BG-1011', 'price' => 18.75, 'stock' => 30, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c3, 'name' => 'Desk Lamp', 'sku' => 'BG-1012', 'price' => 22.40, 'stock' => 19, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

            // Category 4: 3 products
            DB::table('orm_products')->insert(['category_id' => $c4, 'name' => 'PHP Book', 'sku' => 'BG-1013', 'price' => 31.00, 'stock' => 28, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c4, 'name' => 'Clean Code', 'sku' => 'BG-1014', 'price' => 28.50, 'stock' => 12, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c4, 'name' => 'Poetry', 'sku' => 'BG-1015', 'price' => 15.00, 'stock' => 45, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

            // Category 5: 4 products
            DB::table('orm_products')->insert(['category_id' => $c5, 'name' => 'Yoga Mat', 'sku' => 'BG-1016', 'price' => 21.99, 'stock' => 33, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c5, 'name' => 'Cricket Bat', 'sku' => 'BG-1017', 'price' => 45.00, 'stock' => 16, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c5, 'name' => 'Football', 'sku' => 'BG-1018', 'price' => 19.50, 'stock' => 24, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('orm_products')->insert(['category_id' => $c5, 'name' => 'Water Bottle', 'sku' => 'BG-1019', 'price' => 9.99, 'stock' => 50, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

            // Seed 10 orders and items
            for ($i = 1; $i <= 10; $i++) {
                $orderId = DB::table('orm_orders')->insertGetId([
                    'user_id' => 1,
                    'order_number' => 'ORD-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'total' => 50.00,
                    'status' => 'processing',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('orm_order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => 1,
                    'product_name' => 'Wireless Earbuds',
                    'quantity' => 1,
                    'unit_price' => 39.99,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        public function tearDown(): void
        {
            Schema::dropIfExists('orm_order_items');
            Schema::dropIfExists('orm_orders');
            Schema::dropIfExists('orm_products');
            Schema::dropIfExists('orm_categories');
            DB::disableQueryLog();
        }

        // --- Models ----------------------------------------------------------

        public function testFindReturnsModelWithCasts(): void
        {
            $product = OrmProduct::find(1);
            $this->assertTrue($product instanceof OrmProduct);
            $this->assertTrue($product->exists);
            $this->assertSame(39.99, $product->price, 'decimal cast to float');
            $this->assertTrue(is_int($product->stock));
            $this->assertNull(OrmProduct::find(999999));
            $this->assertThrows(\System\Http\HttpException::class, fn() => OrmProduct::findOrFail(999999));
        }

        public function testCreateSaveOnlyDirtyColumnsAndDelete(): void
        {
            $category = OrmCategory::create(['name' => 'ORM Test', 'slug' => 'orm-test']);
            $this->assertTrue($category->id > 0);
            $this->assertNotSame(null, $category->created_at, 'database defaults are loaded after insert');
            $this->assertFalse($category->isDirty());

            $category->name = 'ORM Renamed';
            $this->assertTrue($category->isDirty('name'));
            $this->assertFalse($category->isDirty('slug'));

            DB::enableQueryLog();
            $category->save();
            $update = DB::getQueryLog()[0];
            $q = \System\Database\Connection::isMysql() ? '`' : '"';
            $this->assertSame("UPDATE {$q}orm_categories{$q} SET {$q}name{$q} = ? WHERE {$q}id{$q} = ?", $update['sql'], 'only the changed column is written');
            $this->assertSame('ORM Renamed', OrmCategory::find($category->id)->name);
            $this->assertSame('orm-test', $category->getOriginal('slug'));

            DB::flushQueryLog();
            $category->save();
            $this->assertCount(0, DB::getQueryLog(), 'saving an unchanged model runs no query');

            $this->assertTrue($category->delete());
            $this->assertNull(OrmCategory::find($category->id));
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
            $p = OrmProduct::create(['name' => 'ORM Mug', 'sku' => 'ORM-1', 'price' => 3.5, 'stock' => 2, 'category_id' => 1]);
            $p->update(['price' => 4.25]);
            $this->assertSame(4.25, OrmProduct::find($p->id)->price);
            $this->assertSame(1, OrmProduct::destroy([$p->id, 999999]));
        }

        // --- Relations ---------------------------------------------------------

        public function testRelationsAsMethodsAndProperties(): void
        {
            $order = OrmOrder::find(1);
            $items = $order->items;
            $this->assertTrue(count($items) >= 1);
            $this->assertTrue($items[0] instanceof OrmOrderItem);
            $this->assertSame($order->id, $items[0]->order->id, 'belongsTo back to the order');
            $this->assertSame(User::class, get_class($order->user));

            $bigItems = $order->items()->where('quantity', '>=', 1)->orderBy('id', 'DESC')->get();
            $this->assertCount(count($items), $bigItems, 'a relation can be queried further');

            $this->assertCount(4, OrmCategory::find(2)->products, 'Fashion has 4 products');
            $this->assertSame('Electronics', OrmProduct::find(1)->category->name);
        }

        public function testHasManyCreateFillsForeignKey(): void
        {
            $category = OrmCategory::create(['name' => 'ORM Parent', 'slug' => 'orm-parent']);
            $product = $category->products()->create(['name' => 'ORM Child', 'sku' => 'ORM-2', 'price' => 1, 'stock' => 1]);
            $this->assertSame($category->id, $product->category_id);
            $category->delete();
        }

        public function testEagerLoadingUsesOneQueryPerRelation(): void
        {
            DB::enableQueryLog();
            $orders = OrmOrder::with('items', 'user')->orderBy('id')->limit(10)->get();
            foreach ($orders as $order) {
                $order->items;      // already loaded
                $order->user->name; // already loaded
            }
            $this->assertCount(10, $orders);
            $this->assertCount(3, DB::getQueryLog(), '1 query for orders + 1 for all items + 1 for all users');

            DB::flushQueryLog();
            foreach (OrmOrder::query()->orderBy('id')->limit(10)->get() as $order) {
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
            $sql = DB::table('orm_products')->where('status', 'active')
                ->where(fn($q) => $q->where('price', '<', 10)->orWhere('stock', '<', 5))
                ->orWhereNull('category_id')->toSql();
            $q = \System\Database\Connection::isMysql() ? '`' : '"';
            $this->assertSame("SELECT * FROM {$q}orm_products{$q} WHERE {$q}status{$q} = ? AND ({$q}price{$q} < ? OR {$q}stock{$q} < ?) OR {$q}category_id{$q} IS NULL", $sql);

            $rows = DB::table('orm_products')->where('status', 'active')
                ->where(fn($q) => $q->where('price', '<', 10)->orWhere('sku', 'BG-1000'))->pluck('sku');
            $this->assertTrue(in_array('BG-1000', $rows, true));
            $this->assertTrue(in_array('BG-1019', $rows, true), 'Water Bottle costs 9.99');
        }

        public function testGroupByHavingAndAggregates(): void
        {
            $perCategory = DB::table('orm_products')->select('category_id', 'COUNT(*) AS n')->groupBy('category_id')
                ->having('COUNT(*)', '>=', 4)->orderBy('category_id')->pluck('n', 'category_id');
            $this->assertEquals([1 => 5, 2 => 4, 3 => 4, 5 => 4], $perCategory);

            $this->assertSame(4, DB::table('orm_products')->groupBy('category_id')->having('COUNT(*)', '>=', 4)->count(), 'count of groups');
            $this->assertEquals(129.0, (float) DB::table('orm_products')->max('price'));
            $this->assertEquals(9.99, (float) DB::table('orm_products')->min('price'));
            $this->assertTrue(DB::table('orm_products')->avg('price') > 9.99);
        }

        public function testPluckValueExistsDistinctBetween(): void
        {
            $this->assertSame('Wireless Earbuds', DB::table('orm_products')->where('id', 1)->value('name'));
            $this->assertSame([1 => 'BG-1000', 2 => 'BG-1001'], DB::table('orm_products')->whereIn('id', [1, 2])->pluck('sku', 'id'));
            $this->assertTrue(DB::table('users')->where('email', 'admin@example.com')->exists());
            $this->assertTrue(DB::table('users')->where('email', 'nobody@nowhere')->doesntExist());
            $this->assertCount(5, DB::table('orm_products')->distinct()->select('category_id')->get());
            $this->assertCount(3, DB::table('orm_products')->whereBetween('id', 2, 4)->get());
            $this->assertCount(18, DB::table('orm_products')->whereNotIn('id', [1, 2])->get());
        }

        public function testIncrementDecrementAndChunk(): void
        {
            $before = (int) DB::table('orm_products')->where('id', 3)->value('stock');
            DB::table('orm_products')->where('id', 3)->increment('stock', 5);
            DB::table('orm_products')->where('id', 3)->decrement('stock', 2);
            $this->assertSame($before + 3, (int) DB::table('orm_products')->where('id', 3)->value('stock'));
            DB::table('orm_products')->where('id', 3)->update(['stock' => $before]);
            $this->assertThrows(\LogicException::class, fn() => DB::table('orm_products')->increment('stock'));

            $seen = 0;
            $batches = 0;
            DB::table('orm_products')->orderBy('id')->chunk(6, function ($rows) use (&$seen, &$batches) {
                $seen += count($rows);
                $batches++;
            });
            $this->assertSame([20, 4], [$seen, $batches]);

            $stopped = 0;
            DB::table('orm_products')->orderBy('id')->chunk(5, function () use (&$stopped) {
                $stopped++;
                return false;
            });
            $this->assertSame(1, $stopped, 'returning false stops chunking');
        }

        // --- Transactions ---------------------------------------------------------

        public function testTransactionCommitsAndRollsBack(): void
        {
            DB::transaction(fn() => DB::table('orm_categories')->insert(['name' => 'ORM Commit', 'slug' => 'orm-commit']));
            $this->assertTrue(DB::table('orm_categories')->where('slug', 'orm-commit')->exists());

            try {
                DB::transaction(function () {
                    DB::table('orm_categories')->insert(['name' => 'ORM Rollback', 'slug' => 'orm-rollback']);
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException $e) {
            }
            $this->assertFalse(DB::table('orm_categories')->where('slug', 'orm-rollback')->exists(), 'rolled back');
            $this->assertSame(0, DB::transactionLevel());
        }

        public function testNestedTransactionsUseSavepoints(): void
        {
            DB::transaction(function () {
                DB::table('orm_categories')->insert(['name' => 'ORM Outer', 'slug' => 'orm-outer']);
                try {
                    DB::transaction(function () {
                        DB::table('orm_categories')->insert(['name' => 'ORM Inner', 'slug' => 'orm-inner']);
                        throw new \RuntimeException('inner fails');
                    });
                } catch (\RuntimeException $e) {
                }
            });

            $this->assertTrue(DB::table('orm_categories')->where('slug', 'orm-outer')->exists(), 'outer work is kept');
            $this->assertFalse(DB::table('orm_categories')->where('slug', 'orm-inner')->exists(), 'only the inner part is undone');
        }

        public function testQueryLogRecordsSqlBindingsAndTime(): void
        {
            DB::enableQueryLog();
            DB::table('users')->where('email', 'admin@example.com')->first();
            $log = DB::getQueryLog();
            $q = \System\Database\Connection::isMysql() ? '`' : '"';
            $this->assertSame("SELECT * FROM {$q}users{$q} WHERE {$q}email{$q} = ? LIMIT 1", $log[0]['sql']);
            $this->assertSame(['admin@example.com'], $log[0]['bindings']);
            $this->assertTrue(is_float($log[0]['ms']) || is_int($log[0]['ms']));
        }

        public function testReservedWordColumnsWorkInQueries(): void
        {
            Schema::dropIfExists('orm_reserved_settings');
            Schema::create('orm_reserved_settings', function (Blueprint $t) {
                $t->id();
                $t->string('key')->unique();
                $t->string('order');
                $t->string('group');
                $t->string('value');
            });

            // Insert with reserved words
            DB::table('orm_reserved_settings')->insert([
                'key' => 'app_name',
                'order' => '1',
                'group' => 'general',
                'value' => 'Bongo',
            ]);

            // Select & Where with reserved words
            $row = DB::table('orm_reserved_settings')
                ->where('key', 'app_name')
                ->where('group', 'general')
                ->orderBy('order', 'ASC')
                ->first();

            $this->assertNotNull($row);
            $this->assertSame('Bongo', $row->value);

            // Update with reserved words
            DB::table('orm_reserved_settings')
                ->where('key', 'app_name')
                ->update(['value' => 'BongoMvc', 'order' => '2']);

            $this->assertSame('BongoMvc', DB::table('orm_reserved_settings')->where('key', 'app_name')->value('value'));

            // Upsert with reserved words
            DB::table('orm_reserved_settings')->upsert([
                ['key' => 'app_name', 'order' => '3', 'group' => 'general', 'value' => 'Bongo Framework'],
                ['key' => 'theme', 'order' => '4', 'group' => 'ui', 'value' => 'dark'],
            ], ['value', 'order']);

            $this->assertSame('Bongo Framework', DB::table('orm_reserved_settings')->where('key', 'app_name')->value('value'));
            $this->assertSame('dark', DB::table('orm_reserved_settings')->where('key', 'theme')->value('value'));

            // Group by reserved word
            $groups = DB::table('orm_reserved_settings')->groupBy('group')->pluck('group');
            $this->assertCount(2, $groups);

            Schema::dropIfExists('orm_reserved_settings');
        }
    }
}
