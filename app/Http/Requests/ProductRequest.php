<?php

namespace App\Http\Requests;

use System\Http\FormRequest;

/**
 * The product form (create and edit). On /products/{product} the product's own SKU
 * is allowed by the unique rule.
 */
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'name'         => 'required|string|max:150',
            'sku'          => 'required|string|max:50|unique:products,sku' . ($id ? ',' . (int) $id : ''),
            'category_id'  => 'nullable|integer|exists:categories,id',
            'price'        => 'required|numeric|min:0|max:99999999',
            'stock'        => 'required|integer|min:0|max:1000000',
            'status'       => 'required|in:active,draft',
            'description'  => 'nullable|string|max:5000',
            'image'        => 'nullable|image|max:2048',   // KB
            'remove_image' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return ['category_id' => 'category'];
    }

    /** "No category" arrives as an empty string */
    protected function prepare(array $data): array
    {
        $data['category_id'] = ($data['category_id'] ?? null) ?: null;
        return $data;
    }
}
