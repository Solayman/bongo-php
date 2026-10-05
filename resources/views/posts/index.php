@extends('layouts.admin')

@set('title', __('Blog Posts'))

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form method="GET" action="<?= url('/posts') ?>" class="d-flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search posts..." style="width: 220px;">
        <select name="status" class="form-select" style="width: 140px;">
            <option value="">Any status</option>
            <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
            <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
    </form>
    <a href="<?= url('/posts/create') ?>" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i>New post</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 56px;"></th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($posts['data'] as $post)
                <tr>
                    <td>
                        @if($post->featured_image)
                            <img src="{{ $post->featuredImageUrl() }}" alt="" class="rounded" style="width: 44px; height: 44px; object-fit: cover;">
                        @else
                            <div class="rounded bg-light border d-flex align-items-center justify-content-center text-secondary" style="width: 44px; height: 44px;">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                        @endif
                    </td>
                    <td class="fw-medium">
                        <a href="<?= url('/posts/' . $post->id . '/edit') ?>" class="text-decoration-none text-dark">
                            {{ $post->title }}
                        </a>
                    </td>
                    <td class="text-secondary small">{{ $post->slug }}</td>
                    <td>
                        <span class="badge <?= $post->status === 'published' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            {{ ucfirst($post->status) }}
                        </span>
                    </td>
                    <td class="text-secondary small"><?= date('M d, Y', strtotime($post->created_at)) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('/blog/' . $post->slug) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View in frontend">
                            <i class="bi bi-box-arrow-up-right"></i> View
                        </a>
                        <a href="<?= url('/posts/' . $post->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                            Edit
                        </a>
                        <form method="POST" action="<?= url('/posts/' . $post->id) ?>" class="d-inline" onsubmit="return confirm('Delete this post?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-secondary">
                        No blog posts found. <a href="<?= url('/posts/create') ?>">Write your first post</a>.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(($posts['last_page'] ?? 1) > 1)
        <div class="card-footer bg-white border-top-0 py-3">
            @include('partials.pagination', ['paginator' => $posts])
        </div>
    @endif
</div>
@endsection
