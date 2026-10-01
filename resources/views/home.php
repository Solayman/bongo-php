@extends('layouts.app')

@set('title', 'Home')

@section('content')
<div class="card">
    <div class="card-body">
        <h1 class="h4">You're signed in</h1>
        <p class="mb-0 text-secondary">
            Signed in as <strong>{{ auth()->name }}</strong> ({{ auth()->email }}).
            This page is protected by the <code>auth</code> middleware. Build your app from here.
        </p>
    </div>
</div>
@endsection
