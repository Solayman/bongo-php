<?php
namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use System\Database\DB;
use System\Testing\TestCase;

class QueryBuilderTest extends TestCase
{
    public function testBuildsSelectSql(): void
    {
        $sql = DB::table('products')
            ->select('products.id', 'categories.name AS category')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('price', '>', 10)
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('price', 'desc')
            ->limit(5)
            ->offset(10)
            ->toSql();

        $this->assertSame(
            'SELECT products.id, categories.name AS category FROM products LEFT JOIN categories ON categories.id = products.category_id'
            . ' WHERE price > ? AND status IN (?,?) ORDER BY price DESC LIMIT 5 OFFSET 10',
            $sql
        );
    }

    public function testRejectsBadOperatorAndDirection(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('users')->where('id', '; DROP', 1));
        $this->assertThrows(\InvalidArgumentException::class, fn() => DB::table('users')->orderBy('id', 'DESC; DROP'));
    }

    public function testCrudRoundTrip(): void
    {
        $id = DB::table('categories')->insertGetId(['name' => 'Temp', 'slug' => 'temp-qb']);
        $this->assertTrue($id > 0);
        $this->assertSame('Temp', DB::table('categories')->where('id', $id)->first()->name);

        DB::table('categories')->where('id', $id)->update(['name' => 'Temp 2']);
        $this->assertSame('Temp 2', DB::table('categories')->where('id', $id)->first()->name);

        DB::table('categories')->where('id', $id)->delete();
        $this->assertNull(DB::table('categories')->where('id', $id)->first());
    }

    public function testRefusesToUpdateOrDeleteEveryRow(): void
    {
        $this->assertThrows(\LogicException::class, fn() => DB::table('products')->update(['stock' => 0]));
        $this->assertThrows(\LogicException::class, fn() => DB::table('products')->delete());
    }

    public function testAggregatesAndPaginate(): void
    {
        $this->assertSame(20, DB::table('products')->count());
        $this->assertSame(10, User::where('role', 'customer')->count());
        $this->assertTrue(DB::table('orders')->sum('total') > 0);

        $page = DB::table('products')->orderBy('id')->paginate(6, 4);
        $this->assertSame(20, $page['total']);
        $this->assertSame(4, $page['last_page']);
        $this->assertCount(2, $page['data']);
    }

    public function testModelHelpers(): void
    {
        $this->assertSame('admin@example.com', User::find(1)->email);
        $this->assertSame('admin', User::where('email', 'admin@example.com')->first()->role);
        $this->assertCount(20, Product::all());
        $this->assertSame('products', Product::tableName());
    }
}
