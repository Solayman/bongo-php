<?php
namespace System\Events;

/**
 * Minimal event dispatcher.
 *
 *   Event::listen('order.shipped', fn($order) => Mail::to($order->email)->...->send());
 *   Event::listen(OrderShipped::class, [NotifyCustomer::class, 'handle']);
 *   Event::dispatch('order.shipped', $order);
 *   Event::dispatch(new OrderShipped($order));   // listeners registered for the class name
 *
 * Listeners run in the order they were added. A listener returning false stops the rest.
 */
class Event
{
    protected static array $listeners = [];

    public static function listen(string $event, callable|array|string $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /** @return array results of the listeners that ran */
    public static function dispatch(string|object $event, ...$payload): array
    {
        if (is_object($event)) {
            $payload = [$event];
            $event = get_class($event);
        }

        $results = [];
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $result = \System\Container\Container::getInstance()->call(self::resolve($listener), $payload);
            $results[] = $result;
            if ($result === false) {
                break;
            }
        }
        return $results;
    }

    public static function hasListeners(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    public static function forget(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
        } else {
            unset(self::$listeners[$event]);
        }
    }

    /** "Class@method" or "Class" (→ handle) into a callable */
    protected static function resolve(callable|array|string $listener): callable|array
    {
        if (is_string($listener) && !is_callable($listener)) {
            [$class, $method] = array_pad(explode('@', $listener, 2), 2, 'handle');
            return [$class, $method];
        }
        return $listener;
    }
}
