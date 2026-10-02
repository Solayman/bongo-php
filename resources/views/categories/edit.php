@extends('layouts.admin')

@set('title', 'Edit category')

@section('content')
<a href="<?= url('/categories') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to categories</a>
<div class="card mt-3" style="max-width: 480px;">
    <div class="card-body">
        <form method="POST" action="<?= url('/categories/' . $category->id) ?>" novalidate>
            <?= csrf_field() ?>
            <label for="name" class="form-label">Name</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" class="form-control <?= error('name') ? 'is-invalid' : '' ?>">
            <div class="invalid-feedback">{{ error('name') }}</div>
            <div class="form-text">Slug: {{ $category->slug }} (updated from the name)</div>
            <button type="submit" class="btn btn-dark mt-3">Save changes</button>
        </form>
    </div>
</div>
@endsection
