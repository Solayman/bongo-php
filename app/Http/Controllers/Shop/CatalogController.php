<?php
namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use System\Http\HttpException;
use System\Http\Request;

class CatalogController extends Controller
{
    /** GET /shop */
    public function index(Request $request)
    {
        $query = Product::query()->where('status', 'active')->orderBy('name');

        if ($q = $request->q) {
            $query->whereRaw('name LIKE ? OR description LIKE ?', ["%$q%", "%$q%"]);
        }
        $category = null;
        if ($slug = $request->category) {
            $category = Category::where('slug', $slug)->first();
            $query->where('category_id', $category->id ?? 0);
        }

        return $this->view('shop.index', [
            'products'   => $query->paginate(12),
            'categories' => Category::query()->orderBy('name')->get(),
            'category'   => $category,
            'search'     => $q,
        ]);
    }

    /** GET /shop/products/{product} */
    public function show(Product $product)
    {
        if ($product->status !== 'active') {
            throw new HttpException(404, 'Product not found');
        }
        return $this->view('shop.product', [
            'product' => $product,
            'related' => Product::where('category_id', $product->category_id)->where('id', '!=', $product->id)
                ->where('status', 'active')->limit(4)->get(),
        ]);
    }
}
