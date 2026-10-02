@extends('layouts.admin')

@set('title', 'Product')

@section('content')
<?php
$editing = $product !== null;
// Old input wins over saved values after a failed validation
$value = fn(string $field, $default = '') => old($field, $editing ? ($product->$field ?? $default) : $default);
$invalid = fn(string $field) => error($field) ? 'is-invalid' : '';
?>
<div class="mb-3">
    <a href="<?= url('/products') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to products</a>
    <h2 class="h4 mt-2"><?= $editing ? 'Edit ' . e($product->name) : 'New product' ?></h2>
</div>

<form method="POST" action="<?= $editing ? route('products.update', ['product' => $product->id]) : route('products.store') ?>" enctype="multipart/form-data" novalidate>
    @csrf
    <?= $editing ? method_field('PUT') : '' ?>
    <div class="card"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="name">Name</label>
                <input class="form-control <?= $invalid('name') ?>" id="name" name="name" value="{{ $value('name') }}">
                <div class="invalid-feedback">{{ error('name') }}</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sku">SKU</label>
                <input class="form-control <?= $invalid('sku') ?>" id="sku" name="sku" value="{{ $value('sku') }}">
                <div class="invalid-feedback">{{ error('sku') }}</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select <?= $invalid('category_id') ?>" id="category_id" name="category_id">
                    <option value="">Uncategorised</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" <?= (string) $value('category_id') === (string) $category->id ? 'selected' : '' ?>>{{ $category->name }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback">{{ error('category_id') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="price">Price ($)</label>
                <input class="form-control <?= $invalid('price') ?>" id="price" name="price" type="number" step="0.01" min="0" value="{{ $value('price') }}">
                <div class="invalid-feedback">{{ error('price') }}</div>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="stock">Stock</label>
                <input class="form-control <?= $invalid('stock') ?>" id="stock" name="stock" type="number" min="0" value="{{ $value('stock', '0') }}">
                <div class="invalid-feedback">{{ error('stock') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select <?= $invalid('status') ?>" id="status" name="status">
                    <option value="active" <?= $value('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="draft" <?= $value('status', 'active') === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
                <div class="invalid-feedback">{{ error('status') }}</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control <?= $invalid('description') ?>" id="description" name="description" rows="4">{{ $value('description') }}</textarea>
                <div class="invalid-feedback">{{ error('description') }}</div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="image">Image</label>
                <input class="form-control <?= $invalid('image') ?>" id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="invalid-feedback">{{ error('image') }}</div>
                <div class="form-text">JPG, PNG, GIF or WebP, up to 2 MB.</div>
            </div>
            @if($editing && $product->image)
                <div class="col-md-4">
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="img-thumbnail" style="max-height: 120px;">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                        <label class="form-check-label" for="remove_image">Remove image</label>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="card-footer bg-white text-end">
        <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-dark"><?= $editing ? 'Save changes' : 'Create product' ?></button>
    </div></div>
</form>
@endsection
