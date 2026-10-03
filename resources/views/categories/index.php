@extends('layouts.admin')

@set('title', __('Categories'))

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">New category</div>
            <div class="card-body">
                <form method="POST" action="<?= url('/categories') ?>" novalidate>
                    @csrf
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" class="form-control <?= error('name') ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback">{{ error('name') }}</div>
                    <button type="submit" class="btn btn-dark mt-3 w-100">Add category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Slug</th><th class="num">Products</th><th></th></tr></thead>
                    <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td class="fw-medium">{{ $category->name }}</td>
                            <td class="text-secondary small">{{ $category->slug }}</td>
                            <td class="num"><a href="<?= url('/products?category=' . $category->id) ?>">{{ $category->products_count }}</a></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/categories/' . $category->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="<?= route('categories.destroy', ['id' => $category->id]) ?>" class="d-inline"
                                      onsubmit="return confirm('Delete this category? Its products will become uncategorised.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @if(!$categories)
                        <tr><td colspan="4" class="text-center text-secondary py-4">No categories yet.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
