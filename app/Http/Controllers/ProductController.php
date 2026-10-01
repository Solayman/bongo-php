<?php
namespace App\Http\Controllers;

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
            ->select('products.*', 'categories.name AS category_name')
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

        echo $this->view('products.index', [
            'products'   => $query->paginate(10),
            'categories' => Category::query()->orderBy('name')->get(),
            'filters'    => ['q' => $search, 'category' => $categoryId, 'status' => $status],
        ]);
    }

    public function create()
    {
        echo $this->view('products.form', [
            'product'    => null,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store()
    {
        $data = $this->validate($this->rules());
        $data['category_id'] = ($data['category_id'] ?? null) ?: null;

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $data['image']->store('products');
        } else {
            unset($data['image']);
        }

        Product::create($data);

        flash('success', "Product \"{$data['name']}\" created.");
        redirect(url('/products'));
    }

    public function edit($id)
    {
        echo $this->view('products.form', [
            'product'    => $this->findOrFail($id),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update($id)
    {
        $product = $this->findOrFail($id);
        $data = $this->validate($this->rules((int) $product->id) + ['remove_image' => 'nullable|boolean']);
        $data['category_id'] = ($data['category_id'] ?? null) ?: null;

        $upload = $data['image'] ?? null;
        $remove = filter_var($data['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['image'], $data['remove_image']);

        if ($upload instanceof UploadedFile || $remove) {
            Storage::delete($product->image);   // the old file
            $data['image'] = $upload instanceof UploadedFile ? $upload->store('products') : null;
        }

        $product->update($data);

        flash('success', "Product \"{$product->name}\" updated.");
        redirect(url('/products'));
    }

    public function destroy($id)
    {
        $product = $this->findOrFail($id);
        Storage::delete($product->image);
        $product->delete();

        flash('success', "Product \"{$product->name}\" deleted.");
        redirect(url('/products'));
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'name'        => 'required|string|max:150',
            'sku'         => 'required|string|max:50|unique:products,sku' . ($ignoreId ? ",$ignoreId" : ''),
            'category_id' => 'nullable|integer|exists:categories,id',
            'price'       => 'required|numeric|min:0|max:99999999',
            'stock'       => 'required|integer|min:0|max:1000000',
            'status'      => 'required|in:active,draft',
            'description' => 'nullable|string|max:5000',
            'image'       => 'nullable|image|max:2048',   // KB
        ];
    }

    protected function findOrFail($id): Product
    {
        return Product::findOrFail((int) $id);
    }
}
