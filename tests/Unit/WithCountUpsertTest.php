<?php

namespace Tests\Unit\WithCountProbe {
    use System\Database\Model;

    class WcCategory extends Model
    {
        protected static $table = 'wc_categories';
        protected array $fillable = ['name', 'slug'];

        public function products()
        {
            return $this->hasMany(WcProduct::class, 'category_id');
        }
    }

    class WcProduct extends Model
    {
        protected static $table = 'wc_products';
        protected array $fillable = ['category_id', 'name'];
    }
}

namespace Tests\Unit {

    use System\Database\DB;
    use System\Database\Schema\Blueprint;
    use System\Database\Schema\Schema;
    use System\Testing\TestCase;
    use Tests\Unit\WithCountProbe\WcCategory;
    use Tests\Unit\WithCountProbe\WcProduct;

    class WithCountUpsertTest extends TestCase
    {
        public function setUp(): void
        {
            $this->tearDown();
            Schema::create('wc_categories', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug');
            });
            Schema::create('wc_products', function (Blueprint $t) {
                $t->id();
                $t->integer('category_id');
                $t->string('name');
            });

            $c1 = DB::table('wc_categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics']);
            $c2 = DB::table('wc_categories')->insertGetId(['name' => 'Books', 'slug' => 'books']);

            DB::table('wc_products')->insert(['category_id' => $c1, 'name' => 'Phone']);
            DB::table('wc_products')->insert(['category_id' => $c1, 'name' => 'Laptop']);
            DB::table('wc_products')->insert(['category_id' => $c2, 'name' => 'Novel']);
        }

        public function tearDown(): void
        {
            Schema::dropIfExists('wc_stock');
            Schema::dropIfExists('wc_products');
            Schema::dropIfExists('wc_categories');
        }

        public function testWithCountMatchesARealCount(): void
        {
            $categories = WcCategory::withCount('products')->orderBy('name')->get();
            $this->assertTrue(count($categories) === 2);
            foreach ($categories as $category) {
                $real = DB::table('wc_products')->where('category_id', $category->id)->count();
                $this->assertSame($real, (int) $category->products_count, "{$category->name}: $real products");
                $this->assertNotNull($category->slug, 'the normal columns are still there');
            }

            // Combines with where() and a single model
            $one = WcCategory::withCount('products')->where('wc_categories.id', $categories[0]->id)->first();
            $this->assertSame((int) $categories[0]->products_count, (int) $one->products_count);

            $this->assertThrows(\BadMethodCallException::class, fn() => WcCategory::withCount('nothing')->get());
        }

        public function testUpsertInsertsAndUpdatesInOneQuery(): void
        {
            Schema::create('wc_stock', function (Blueprint $t) {
                $t->id();
                $t->string('sku', 20)->unique();
                $t->integer('qty');
                $t->string('note')->nullable();
            });
            DB::table('wc_stock')->insert(['sku' => 'A1', 'qty' => 1, 'note' => 'keep me']);

            DB::table('wc_stock')->upsert([
                ['sku' => 'A1', 'qty' => 50, 'note' => 'changed'],
                ['sku' => 'B2', 'qty' => 7, 'note' => 'new'],
            ], ['qty']);   // existing rows: only qty changes

            $a = DB::table('wc_stock')->where('sku', 'A1')->first();
            $this->assertSame(50, (int) $a->qty);
            $this->assertSame('keep me', $a->note, 'columns not listed in $update are kept');
            $this->assertSame(7, (int) DB::table('wc_stock')->where('sku', 'B2')->value('qty'));
            $this->assertSame(2, DB::table('wc_stock')->count());

            $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('wc_stock')->upsert([['sku' => 'C', 'qty' => 1], ['qty' => 2, 'sku' => 'D']]));
        }
    }
}
