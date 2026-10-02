@extends('layouts.admin')

@set('title', 'Products')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form method="GET" action="<?= url('/products') ?>" class="d-flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search name or SKU" style="width: 220px;">
        <select name="category" class="form-select" style="width: 180px;">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" <?= (string) $filters['category'] === (string) $category->id ? 'selected' : '' ?>>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="width: 140px;">
            <option value="">Any status</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
    </form>
    <a href="<?= url('/products/create') ?>" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i>New product</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th style="width: 56px;"></th><th>Product</th><th>SKU</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            @foreach($products['data'] as $product)
                <tr>
                    <td>
                        @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                        @else
                            <div class="rounded bg-light border d-flex align-items-center justify-content-center text-secondary" style="width: 40px; height: 40px;"><i class="bi bi-image"></i></div>
                        @endif
                    </td>
                    <td class="fw-medium">{{ $product->name }}</td>
                    <td class="text-secondary small">{{ $product->sku }}</td>
                    <td>{{ $product->category_name ?? '—' }}</td>
                    <td class="num">{{ money($product->price) }}</td>
                    <td class="num <?= $product->stock < 10 ? 'text-danger fw-semibold' : '' ?>">{{ $product->stock }}</td>
                    <td>
                        <span class="badge <?= $product->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">{{ ucfirst($product->status) }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('/products/' . $product->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <form method="POST" action="<?= route('products.destroy', ['product' => $product->id]) ?>" class="d-inline"
                              onsubmit="return confirm('Delete this product?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            @if(!$products['data'])
                <tr><td colspan="8" class="text-center text-secondary py-4">No products found.</td></tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

<?php $page = $products; ?>
@include('partials.pagination')
@endsection
