{: php $customer = auth('customer') :}
{: php $cartCount = app(App\Services\Cart::class)->count() :}
<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{: show 'title' :} · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('asset/css/shop.css') }}" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg shop-nav border-bottom">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('shop.index') }}"><i class="bi bi-bag-heart-fill me-2"></i>{{ config('app.name') }}</a>
        <form class="d-none d-md-flex flex-grow-1 mx-4" method="GET" action="{{ route('shop.index') }}" role="search">
            <input class="form-control" type="search" name="q" value="{{ $search ?? '' }}" placeholder="{{ __('Search products') }}" aria-label="{{ __('Search products') }}">
        </form>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-translate"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    {: each System\Translation\Lang::locales() as $code => $label :}
                        <li><a class="dropdown-item {: if $code === locale() :} active {: end :}" href="?lang={{ $code }}" lang="{{ $code }}">{{ $label }}</a></li>
                    {: end :}
                </ul>
            </div>
            <a href="{{ route('shop.cart') }}" class="btn btn-sm btn-dark position-relative">
                <i class="bi bi-cart3 me-1"></i>{{ __('Cart') }}
                {: if $cartCount :}
                    <span class="badge rounded-pill text-bg-warning ms-1">{{ $cartCount }}</span>
                {: end :}
            </a>
            {: if $customer :}
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>{{ $customer->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('shop.orders') }}">{{ __('My orders') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('shop.logout') }}" class="m-0">
                                {: csrf :}
                                <button class="dropdown-item" type="submit">{{ __('Sign out') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            {: else :}
                <a href="{{ route('shop.login') }}" class="btn btn-sm btn-outline-dark">{{ __('Sign in') }}</a>
            {: end :}
        </div>
    </div>
</nav>

<main class="container py-4">
    {: if $message = flash('success') :}
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    {: end :}
    {: if $message = flash('error') :}
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    {: end :}
    {: show 'content' :}
</main>

<footer class="border-top py-4 mt-4 text-secondary small">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
        <span>{{ config('app.name') }} · {{ __('A demo shop built on BongoMVC') }}</span>
        <a href="{{ url('/login') }}" class="text-secondary">{{ __('Admin') }}</a>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{: stack 'scripts' :}
</body>
</html>
