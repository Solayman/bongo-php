<?php
namespace System\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use System\Database\Model;
use System\Http\HttpException;

/**
 * A small service container.
 *
 *   app()->singleton(Mailer::class, fn() => new Mailer(config('mail')));
 *   app()->bind(PaymentGateway::class, StripeGateway::class);
 *   $mailer = app(Mailer::class);           // same instance every time
 *   $report = app(ReportBuilder::class);    // built automatically, constructor dependencies included
 *
 * The router uses call() for controller actions, so methods can ask for what they need:
 *   public function edit(Product $product, Request $request) { ... }   // route /products/{product}/edit
 */
class Container
{
    protected static ?Container $instance = null;

    /** abstract => [factory, shared] */
    protected array $bindings = [];
    protected array $instances = [];
    protected array $building = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    /** A new object on every make() */
    public function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = [$concrete ?? $abstract, false];
        unset($this->instances[$abstract]);
    }

    /** One shared object, built on first make() */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = [$concrete ?? $abstract, true];
        unset($this->instances[$abstract]);
    }

    /** Register an existing object */
    public function instance(string $abstract, object $object): object
    {
        return $this->instances[$abstract] = $object;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        [$concrete, $shared] = $this->bindings[$abstract] ?? [$abstract, false];

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : ($concrete === $abstract ? $this->build($concrete, $parameters) : $this->make($concrete, $parameters));

        // e.g. a FormRequest validates the input as soon as it exists
        if ($object instanceof WhenResolved && $concrete === $abstract) {
            $object->resolved();
        }

        if ($shared) {
            $this->instances[$abstract] = $object;
        }
        return $object;
    }

    /** Create $class, resolving its constructor parameters */
    public function build(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Cannot build [$class]: class not found");
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Cannot build [$class]: it is abstract or an interface. Bind it with app()->bind($class::class, Concrete::class).");
        }
        if (isset($this->building[$class])) {
            throw new ContainerException("Circular dependency while building [$class]");
        }

        $this->building[$class] = true;
        try {
            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return new $class();
            }
            return $reflection->newInstanceArgs($this->resolveParameters($constructor, $parameters));
        } finally {
            unset($this->building[$class]);
        }
    }

    /**
     * Call a closure or [object|class, method], filling its parameters from
     * $parameters (by name, then in order), models by id, and the container.
     */
    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable) && is_string($callable[0])) {
            $callable[0] = $this->make($callable[0]);
        }

        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction(Closure::fromCallable($callable));

        return $callable(...$this->resolveParameters($reflection, $parameters));
    }

    protected function resolveParameters(ReflectionFunctionAbstract $function, array $given): array
    {
        $named = array_filter($given, 'is_string', ARRAY_FILTER_USE_KEY);
        $positional = array_values($given);
        $usedNames = [];
        $position = 0;
        $args = [];

        // Next value not already taken by name
        $nextPositional = function () use (&$position, &$usedNames, $given) {
            $keys = array_keys($given);
            while ($position < count($keys)) {
                $key = $keys[$position++];
                if (!in_array($key, $usedNames, true)) {
                    $usedNames[] = $key;
                    return [true, $given[$key]];
                }
            }
            return [false, null];
        };

        foreach ($function->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $class = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

            if ($param->isVariadic()) {
                break;
            }

            // A value from the route/arguments for this parameter, if any
            [$found, $value] = array_key_exists($name, $named) ? [true, $named[$name]] : [false, null];
            if ($found) {
                $usedNames[] = $name;
            }

            if ($class !== null && is_subclass_of($class, Model::class)) {
                if (!$found) {
                    [$found, $value] = $nextPositional();
                }
                $args[] = $this->resolveModel($class, $found ? $value : null, $param);
                continue;
            }

            if ($class !== null) {
                if (!($found && $value instanceof $class)) {
                    // An object passed in (e.g. an event) fills a parameter of its type
                    $found = false;
                    foreach ($given as $key => $candidate) {
                        if (!in_array($key, $usedNames, true) && $candidate instanceof $class) {
                            $usedNames[] = $key;
                            [$found, $value] = [true, $candidate];
                            break;
                        }
                    }
                }
                $args[] = $found ? $value : $this->make($class);
                continue;
            }

            if (!$found) {
                [$found, $value] = $nextPositional();
            }
            if ($found && $value !== null) {
                $args[] = $value;
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull() || $found) {
                $args[] = null;
            } else {
                throw new ContainerException("No value for parameter \$$name of " . $this->describe($function));
            }
        }

        return $args;
    }

    /** Route model binding: the id from the URL becomes the model, or a 404 */
    protected function resolveModel(string $class, $value, ReflectionParameter $param): ?Model
    {
        if ($value instanceof $class) {
            return $value;
        }
        if ($value === null || $value === '') {
            if ($param->allowsNull()) return null;
            throw new HttpException(404, (new ReflectionClass($class))->getShortName() . ' not found');
        }
        return $class::findOrFail($value);
    }

    protected function describe(ReflectionFunctionAbstract $function): string
    {
        return $function instanceof ReflectionMethod
            ? $function->getDeclaringClass()->getName() . '::' . $function->getName() . '()'
            : 'closure';
    }
}
