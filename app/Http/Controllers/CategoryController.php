<?php
namespace App\Http\Controllers;

use App\Models\Category;
use System\Database\DB;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = DB::raw(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name'
        );

        echo $this->view('categories.index', ['categories' => $categories]);
    }

    public function store()
    {
        $data = $this->validate(['name' => 'required|string|max:100']);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Category::create($data);

        flash('success', "Category \"{$data['name']}\" created.");
        redirect(url('/categories'));
    }

    public function edit($id)
    {
        echo $this->view('categories.edit', ['category' => $this->findOrFail($id)]);
    }

    public function update($id)
    {
        $category = $this->findOrFail($id);
        $data = $this->validate(['name' => 'required|string|max:100']);
        $data['slug'] = $this->uniqueSlug($data['name'], (int) $category->id);

        Category::query()->where('id', $category->id)->update($data);

        flash('success', "Category \"{$data['name']}\" updated.");
        redirect(url('/categories'));
    }

    public function destroy($id)
    {
        $category = $this->findOrFail($id);
        // products.category_id is ON DELETE SET NULL, so products are kept
        Category::query()->where('id', $category->id)->delete();

        flash('success', "Category \"{$category->name}\" deleted. Its products are now uncategorised.");
        redirect(url('/categories'));
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'category';
        $slug = $base;
        for ($i = 2; ; $i++) {
            $query = Category::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->count() === 0) {
                return $slug;
            }
            $slug = "$base-$i";
        }
    }

    protected function findOrFail($id): object
    {
        return Category::find((int) $id) ?? abort(404, 'Category not found');
    }
}
