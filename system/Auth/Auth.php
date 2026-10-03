<?php
namespace System\Auth;

/**
 * Entry point to authentication.
 *
 *   Auth::guard('api')->user();      // a specific guard
 *   Auth::user(); Auth::check();     // the default guard (config/auth.php → default)
 *   Auth::attempt($email, $pass);    // default guard, session login
 *
 * Guards are configured in config/auth.php. Drivers: "session" and "jwt";
 * register more with Auth::extend('name', fn($name, $provider, $config) => new MyGuard(...)).
 */
class Auth
{
    /** @var Guard[] */
    protected static array $guards = [];
    protected static ?string $default = null;
    protected static array $drivers = [];

    public static function guard(?string $name = null): Guard
    {
        $name ??= self::defaultGuard();

        if (!isset(self::$guards[$name])) {
            $config = config("auth.guards.$name");
            if (!is_array($config)) {
                throw new \InvalidArgumentException("Auth guard [$name] is not defined in config/auth.php");
            }
            $provider = UserProvider::fromConfig($config['provider'] ?? 'users');
            $driver = $config['driver'] ?? 'session';

            self::$guards[$name] = match (true) {
                isset(self::$drivers[$driver]) => (self::$drivers[$driver])($name, $provider, $config),
                $driver === 'session' => new SessionGuard($name, $provider, $config),
                $driver === 'jwt' => new JwtGuard($name, $provider, $config),
                default => throw new \InvalidArgumentException("Auth driver [$driver] is not supported"),
            };
        }

        return self::$guards[$name];
    }

    /** Make $name the default guard for the rest of the request (used by the auth middleware) */
    public static function shouldUse(string $name): void
    {
        self::$default = $name;
    }

    public static function defaultGuard(): string
    {
        return self::$default ?? (string) config('auth.default', 'web');
    }

    /** Register a custom guard driver */
    public static function extend(string $driver, callable $factory): void
    {
        self::$drivers[$driver] = $factory;
    }

    /** Drop resolved guards (used by tests) */
    public static function reset(): void
    {
        self::$guards = [];
        self::$default = null;
    }

    // --- Shortcuts to the default guard (backward compatible API) ------

    public static function user(): ?object
    {
        return self::guard()->user();
    }

    public static function check(): bool
    {
        return self::guard()->check();
    }

    public static function id(): ?int
    {
        return self::guard()->id();
    }

    /** Auth::attempt($email, $password, remember: true) keeps the user signed in after the browser closes */
    public static function attempt(string $identifier, string $password, bool $remember = false): bool
    {
        $guard = self::guard();
        return (bool) ($guard instanceof SessionGuard ? $guard->attempt($identifier, $password, $remember) : $guard->attempt($identifier, $password));
    }

    public static function login(object $user): void
    {
        self::guard()->login($user);
    }

    public static function logout(): void
    {
        self::guard()->logout();
    }

    public static function refresh(): void
    {
        self::guard()->refreshUser();
    }
}
