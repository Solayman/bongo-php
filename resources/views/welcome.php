@extends('layouts.app')

@set('title', 'Welcome')

@section('content')
<div class="hero text-center">
    <i class="bi bi-boxes display-3"></i>
    <h1 class="display-6 fw-semibold mt-3">{{ config('app.name') }}</h1>
    <p class="lead text-secondary">A lightweight PHP MVC framework. No Composer, no build step: just PHP.</p>

    <div class="text-start mx-auto mt-4" style="max-width: 520px;">
        <ol class="list-group list-group-numbered">
            <li class="list-group-item">Add a route in <code>routes/web.php</code></li>
            <li class="list-group-item">Create a controller in <code>app/Http/Controllers</code></li>
            <li class="list-group-item">Write a view in <code>resources/views</code></li>
            <li class="list-group-item">Create tables with <code>php bongo make:migration</code></li>
            <li class="list-group-item">Run the tests with <code>php bongo test</code></li>
        </ol>
    </div>

    <p class="text-secondary small mt-4 mb-0">
        PHP {{ PHP_VERSION }} · Environment: {{ config('app.env') }} · See DOCUMENTATION.md
    </p>
</div>
@endsection
