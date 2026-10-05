@extends('layouts.shop')

@set('title', __('Blog'))

@section('content')
<div class="row align-items-center mb-5 pb-3 border-bottom">
    <div class="col-md-7">
        <h1 class="display-6 fw-bold mb-2">Our Blog</h1>
        <p class="text-secondary lead mb-0">Discover stories, updates, product highlights, and articles.</p>
    </div>
    <div class="col-md-5 mt-3 mt-md-0">
        <form method="GET" action="<?= url('/blog') ?>" class="d-flex gap-2">
            <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control" placeholder="Search blog articles...">
            <button class="btn btn-dark" type="submit">Search</button>
            @if(!empty($search))
                <a href="<?= url('/blog') ?>" class="btn btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="row g-4">
    @forelse($posts['data'] as $post)
        <div class="col-md-6 col-lg-4">
            <article class="card h-100 shadow-sm border-0 bg-light">
                @if($post->featured_image)
                    <a href="<?= url('/blog/' . $post->slug) ?>" class="ratio ratio-16x9 overflow-hidden rounded-top">
                        <img src="{{ $post->featuredImageUrl() }}" class="card-img-top object-fit-cover transition-transform" alt="{{ $post->title }}">
                    </a>
                @else
                    <div class="ratio ratio-16x9 bg-secondary-subtle d-flex align-items-center justify-content-center text-secondary rounded-top">
                        <i class="bi bi-file-earmark-richtext display-5 m-auto"></i>
                    </div>
                @endif
                <div class="card-body d-flex flex-column">
                    <div class="text-secondary small mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3"></i>
                        <span><?= date('M d, Y', strtotime($post->created_at)) ?></span>
                    </div>
                    <h2 class="h5 card-title fw-bold mb-3">
                        <a href="<?= url('/blog/' . $post->slug) ?>" class="text-dark text-decoration-none stretched-link">
                            {{ $post->title }}
                        </a>
                    </h2>
                    <p class="card-text text-secondary mb-4 flex-grow-1">
                        <?= e(mb_substr(strip_tags((string) $post->content), 0, 140)) ?><?= mb_strlen(strip_tags((string) $post->content)) > 140 ? '...' : '' ?>
                    </p>
                    <div class="mt-auto pt-2 border-top text-primary fw-medium small d-flex align-items-center">
                        Read full article <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12 py-5 text-center">
            <div class="text-secondary mb-3"><i class="bi bi-journal-x display-3"></i></div>
            <h3 class="h4">No articles found</h3>
            <p class="text-secondary">Check back soon for latest announcements and blog posts.</p>
            @if(System\Auth\Auth::check() && (auth()->role ?? '') === 'admin')
                <a href="<?= url('/posts/create') ?>" class="btn btn-primary mt-2">Write a post in Admin</a>
            @endif
        </div>
    @endforelse
</div>

@if(($posts['last_page'] ?? 1) > 1)
    <div class="mt-5 d-flex justify-content-center">
        @include('partials.pagination', ['paginator' => $posts])
    </div>
@endif
@endsection
