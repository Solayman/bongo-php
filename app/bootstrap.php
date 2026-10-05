<?php

// Application boot file: loaded on every request and command, after config and helpers.
// Register event listeners, custom view directives and container bindings here.

// Authorization: abilities here, model rules in app/Policies (found automatically)
System\Auth\Gate::define('manage-system', fn($user) => $user->role === 'admin');
