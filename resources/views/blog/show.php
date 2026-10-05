@extends('layouts.shop')

@set('title', $post->title . ' · ' . __('Blog'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('/shop') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/blog') ?>" class="text-decoration-none">Blog</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page">{{ $post->title }}</li>
            </ol>
        </nav>

        <article class="blog-post">
            <header class="mb-4 pb-3 border-bottom">
                <h1 class="display-5 fw-bold mb-3">{{ $post->title }}</h1>
                <div class="d-flex align-items-center gap-3 text-secondary small">
                    <div>
                        <i class="bi bi-calendar3 me-1"></i> <?= date('F d, Y', strtotime($post->created_at)) ?>
                    </div>
                    @if(System\Auth\Auth::check() && (auth()->role ?? '') === 'admin')
                        <div class="ms-auto">
                            <a href="<?= url('/posts/' . $post->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i> Edit Post
                            </a>
                        </div>
                    @endif
                </div>
            </header>

            @if($post->featured_image)
                <div class="mb-5 text-center">
                    <img src="{{ $post->featuredImageUrl() }}" class="img-fluid rounded shadow-sm" style="max-height: 480px; width: 100%; object-fit: cover;" alt="{{ $post->title }}">
                </div>
            @endif

            <div class="post-content lh-lg fs-5">
                {!! $post->content !!}
            </div>

            <footer class="mt-5 pt-4 border-top d-flex justify-content-between align-items-center">
                <a href="<?= url('/blog') ?>" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-left me-1"></i> Back to Blog
                </a>
                <a href="<?= url('/shop') ?>" class="btn btn-dark">
                    Explore Shop Products <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </footer>
        </article>
    </div>
</div>

<style>
.post-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    margin: 1.5rem 0;
}
.post-content p {
    margin-bottom: 1.25rem;
}
.post-content h2, .post-content h3, .post-content h4 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 700;
}
.post-content blockquote {
    border-left: 4px solid #0d6efd;
    padding-left: 1rem;
    color: #6c757d;
    margin: 1.5rem 0;
    font-style: italic;
}
.post-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    border: 1px solid #e9ecef;
    overflow-x: auto;
}
</style>
@endsection
