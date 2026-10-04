<?php

namespace Tests\Unit;

use App\Models\Category;
use System\Database\DB;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class WithCountUpsertTest extends TestCase
{
    public function tearDown(): void
    {
        Schema::dropIfExists('wc_stock');
    }

    public function testWithCountMatchesARealCount(): void
    {
        $categories = Category::withCount('products')->orderBy('name')->get();
        $this->assertTrue(count($categories) > 0);
        foreach ($categories as $category) {
            $real = DB::table('products')->where('category_id', $category->id)->count();
            $this->assertSame($real, (int) $category->products_count, "{$category->name}: $real products");
            $this->assertNotNull($category->slug, 'the normal columns are still there');
        }

        // Combines with where() and a single model
        $one = Category::withCount('products')->where('categories.id', $categories[0]->id)->first();
        $this->assertSame((int) $categories[0]->products_count, (int) $one->products_count);

        $this->assertThrows(\BadMethodCallException::class, fn() => Category::withCount('nothing')->get());
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
