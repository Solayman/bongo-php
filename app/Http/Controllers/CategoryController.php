<?php
namespace App\Http\Controllers;

use App\Models\Category;
use System\Database\DB;
use System\Http\Request;

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

        return $this->view('categories.index', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $data['slug'] = Category::uniqueSlug($data['name']);

        Category::create($data);

        flash('success', "Category \"{$data['name']}\" created.");
        redirect(url('/categories'));
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);   // 404 page when it doesn't exist

        return $this->view('categories.edit', ['category' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:100']);
        $data['slug'] = Category::uniqueSlug($data['name'], $category->id);

        $category->update($data);

        flash('success', "Category \"{$category->name}\" updated.");
        redirect(url('/categories'));
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();   // its products are kept, without a category

        flash('success', "Category \"{$category->name}\" deleted. Its products are now uncategorised.");
        redirect(url('/categories'));
    }
}
