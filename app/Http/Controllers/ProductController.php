<?php
namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use System\Database\DB;
use System\Http\Request;
use System\Http\UploadedFile;
use System\Storage\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $search = Request::query('q');
        $categoryId = Request::query('category');
        $status = Request::query('status');

        $query = DB::table('products')
            ->select('products.*', 'categories.name AS category_name', '(SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = products.id) AS order_count')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->orderBy('products.id', 'DESC');

        if ($search) {
            $query->whereRaw('products.name LIKE ? OR products.sku LIKE ?', ["%$search%", "%$search%"]);
        }
        if ($categoryId) {
            $query->where('products.category_id', (int) $categoryId);
        }
        if (in_array($status, ['active', 'draft'], true)) {
            $query->where('products.status', $status);
        }

        return $this->view('products.index', [
            'products'   => $query->paginate(10),
            'categories' => Category::query()->orderBy('name')->get(),
            'filters'    => ['q' => $search, 'category' => $categoryId, 'status' => $status],
        ]);
    }

    public function create()
    {
        return $this->view('products.form', [
            'product'    => null,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    // ProductRequest validates the form before this runs (see app/Http/Requests)
    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        unset($data['image'], $data['remove_image']);

        if ($upload = $request->file('image')) {
            $data['image'] = $upload->store('products');
        }

        Product::create($data);

        flash('success', "Product \"{$data['name']}\" created.");
        redirect(url('/products'));
    }

    // Route model binding: /products/{product}/edit gives a Product (or a 404)
    public function edit(Product $product)
    {
        return $this->view('products.form', [
            'product'    => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Product $product, ProductRequest $request)
    {
        $data = $request->validated();
        $upload = $request->file('image');
        $remove = filter_var($data['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['image'], $data['remove_image']);

        if ($upload || $remove) {
            Storage::delete($product->image);   // the old file
            $data['image'] = $upload ? $upload->store('products') : null;
        }

        $product->update($data);

        flash('success', "Product \"{$product->name}\" updated.");
        redirect(url('/products'));
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);   // App\Policies\ProductPolicy: ordered products can't be deleted

        Storage::delete($product->image);
        $product->delete();

        flash('success', "Product \"{$product->name}\" deleted.");
        redirect(url('/products'));
    }

}
