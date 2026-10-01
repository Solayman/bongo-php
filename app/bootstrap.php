<?php
// Application boot file: loaded on every request and command, after config and helpers.
// Register event listeners, custom view directives and container bindings here.

use App\Listeners\SendOrderConfirmation;
use System\Events\Event;

Event::listen('order.placed', [SendOrderConfirmation::class, 'handle']);
