<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use System\Database\DB;
use System\Http\Request;

/**
 * Read-only product API for the e-commerce test app (JWT protected).
 */
class ProductController extends Controller
{
    /** GET /api/products?q=&page= */
    public function index()
    {
        $query = DB::table('products')
            ->select('products.id', 'products.name', 'products.sku', 'products.price', 'products.stock', 'products.status', 'categories.name AS category')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->orderBy('products.id');

        if ($q = Request::query('q')) {
            $query->whereRaw('products.name LIKE ? OR products.sku LIKE ?', ["%$q%", "%$q%"]);
        }

        $page = $query->paginate(min(100, max(1, (int) Request::query('per_page', 15))));

        return [
            'data' => array_map(fn($p) => ['price' => (float) $p->price, 'stock' => (int) $p->stock] + (array) $p, $page['data']),
            'meta' => array_diff_key($page, ['data' => true]),
        ];
    }

    /** GET /api/products/{id} */
    public function show(Product $product)
    {
        return ['data' => $product];
    }
}
