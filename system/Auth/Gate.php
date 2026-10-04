<?php

namespace System\Auth;

use Closure;
use System\Http\HttpException;
use System\Log\Log;

/**
 * Authorization: who may do what.
 *
 * Abilities (app/bootstrap.php):
 *   Gate::define('manage-system', fn($user) => $user->role === 'admin');
 *   Gate::define('view-reports', fn(?object $user) => true);       // ?type: guests are asked too
 *   Gate::before(fn($user, $ability) => $user->role === 'owner' ? true : null);   // null = no opinion
 *
 * Policies: rules for one model class, one method per ability. App\Policies\ProductPolicy is
 * used for App\Models\Product automatically (or register one with Gate::policy()):
 *   class ProductPolicy { public function delete($user, Product $product): bool { ... } }
 *
 * Checking:
 *   Gate::allows('delete', $product)   can('delete', $product)   Gate::authorize('delete', $product) → 403
 *   ->middleware('can:manage-system')  @can('delete', $product) ... @endcan   $this->authorize(...)
 *
 * Without a signed-in user every check is false, unless the callback accepts a null user.
 */
class Gate
{
    protected static array $abilities = [];
    protected static array $policies = [];
    protected static array $before = [];
    /** The user to check; null = the signed-in user of the default guard */
    protected static ?object $user = null;
    protected static bool $userSet = false;

    public static function define(string $ability, Closure $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    /** Gate::policy(Product::class, ProductPolicy::class) */
    public static function policy(string $modelClass, string $policyClass): void
    {
        self::$policies[$modelClass] = $policyClass;
    }

    /** Runs before every check: return true/false to decide, null to continue */
    public static function before(Closure $callback): void
    {
        self::$before[] = $callback;
    }

    public static function has(string $ability): bool
    {
        return isset(self::$abilities[$ability]);
    }

    public static function allows(string $ability, mixed ...$arguments): bool
    {
        return self::check($ability, $arguments, self::currentUser());
    }

    public static function denies(string $ability, mixed ...$arguments): bool
    {
        return !self::allows($ability, ...$arguments);
    }

    /** Stop with 403 when the ability is denied */
    public static function authorize(string $ability, mixed ...$arguments): void
    {
        if (self::denies($ability, ...$arguments)) {
            $user = self::currentUser();
            Log::warning('Authorization denied: {ability} for user {user_id}', [
                'ability' => $ability, 'user_id' => $user->id ?? null,
            ]);
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /** Checks for another user: Gate::forUser($customer)->allows('view', $order) */
    public static function forUser(?object $user): object
    {
        return new class ($user) {
            public function __construct(protected ?object $user)
            {
            }
            public function allows(string $ability, mixed ...$arguments): bool
            {
                return Gate::checkFor($this->user, $ability, $arguments);
            }
            public function denies(string $ability, mixed ...$arguments): bool
            {
                return !$this->allows($ability, ...$arguments);
            }
        };
    }

    /** @internal used by forUser() */
    public static function checkFor(?object $user, string $ability, array $arguments): bool
    {
        return self::check($ability, $arguments, $user);
    }

    /** Use this user for every check (tests, CLI commands); null to go back to the signed-in user */
    public static function actingAs(?object $user): void
    {
        self::$user = $user;
        self::$userSet = $user !== null;
    }

    /** Forget every ability, policy and before-callback (tests) */
    public static function flush(): void
    {
        self::$abilities = self::$policies = self::$before = [];
        self::actingAs(null);
    }

    protected static function currentUser(): ?object
    {
        if (self::$userSet) {
            return self::$user;
        }
        $user = Auth::user();
        return is_object($user) ? $user : null;
    }

    protected static function check(string $ability, array $arguments, ?object $user): bool
    {
        foreach (self::$before as $callback) {
            if ($user === null && !self::acceptsGuests($callback)) {
                continue;
            }
            $result = $callback($user, $ability, ...$arguments);
            if ($result !== null) {
                return (bool) $result;
            }
        }

        // A policy method for the first argument's class wins over a plain ability
        if ($arguments && ($policy = self::policyFor($arguments[0]))) {
            if (method_exists($policy, 'before')) {
                $result = $policy->before($user, $ability, ...$arguments);
                if ($result !== null) {
                    return (bool) $result;
                }
            }
            $method = lcfirst(str_replace(['-', '_'], '', ucwords($ability, '-_')));   // "force-delete" → forceDelete
            if (method_exists($policy, $method)) {
                if ($user === null && !self::acceptsGuests([$policy, $method])) {
                    return false;
                }
                return (bool) $policy->$method($user, ...$arguments);
            }
        }

        if (isset(self::$abilities[$ability])) {
            $callback = self::$abilities[$ability];
            if ($user === null && !self::acceptsGuests($callback)) {
                return false;
            }
            return (bool) $callback($user, ...$arguments);
        }

        return false;   // unknown abilities are denied
    }

    /** The policy for a model object or class name: registered, or App\Policies\{Model}Policy */
    protected static function policyFor(mixed $subject): ?object
    {
        $class = is_object($subject) ? get_class($subject) : (is_string($subject) && class_exists($subject) ? $subject : null);
        if ($class === null) {
            return null;
        }
        $policy = self::$policies[$class] ?? null;
        if ($policy === null) {
            $guess = 'App\\Policies\\' . substr(strrchr('\\' . $class, '\\'), 1) . 'Policy';
            $policy = class_exists($guess) ? $guess : null;
        }
        return $policy ? \System\Container\Container::getInstance()->make($policy) : null;
    }

    /** Does the callback's first parameter allow null (?User $user)? */
    protected static function acceptsGuests(callable $callback): bool
    {
        $reflection = is_array($callback)
            ? new \ReflectionMethod($callback[0], $callback[1])
            : new \ReflectionFunction(Closure::fromCallable($callback));
        $first = $reflection->getParameters()[0] ?? null;
        return $first === null || ($first->hasType() && $first->allowsNull()) || $first->isDefaultValueAvailable();
    }
}
