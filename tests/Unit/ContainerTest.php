<?php

namespace Tests\Unit\ContainerProbe {
    interface Gateway
    {
        public function name(): string;
    }
    class FakeGateway implements Gateway
    {
        public function name(): string
        {
            return 'fake';
        }
    }
    class Clock
    {
        public int $created;
        public function __construct()
        {
            $this->created = random_int(1, PHP_INT_MAX);
        }
    }
    class Checkout
    {
        public function __construct(public Gateway $gateway, public Clock $clock, public int $retries = 3)
        {
        }
    }
    class Loop1
    {
        public function __construct(Loop2 $x)
        {
        }
    }
    class Loop2
    {
        public function __construct(Loop1 $x)
        {
        }
    }
}

namespace Tests\Unit {

    use App\Models\Product;
    use System\Container\Container;
    use System\Container\ContainerException;
    use System\Http\HttpException;
    use System\Http\Request;
    use System\Testing\TestCase;
    use Tests\Unit\ContainerProbe\Checkout;
    use Tests\Unit\ContainerProbe\Clock;
    use Tests\Unit\ContainerProbe\FakeGateway;
    use Tests\Unit\ContainerProbe\Gateway;
    use Tests\Unit\ContainerProbe\Loop1;

    class ContainerTest extends TestCase
    {
        protected Container $c;

        public function setUp(): void
        {
            $this->c = new Container();
        }

        public function testAutowiringAndBindings(): void
        {
            $this->assertThrows(ContainerException::class, fn() => $this->c->make(Checkout::class), 'an interface needs a binding');

            $this->c->bind(Gateway::class, FakeGateway::class);
            $checkout = $this->c->make(Checkout::class);
            $this->assertSame('fake', $checkout->gateway->name());
            $this->assertSame(3, $checkout->retries, 'scalar defaults are used');
            $this->assertNotSame($checkout->clock, $this->c->make(Checkout::class)->clock, 'bind() builds a new object each time');
        }

        public function testSingletonsInstancesAndFactories(): void
        {
            $this->c->singleton(Clock::class);
            $this->assertSame($this->c->make(Clock::class), $this->c->make(Clock::class));

            $this->c->bind('greeting', fn() => 'hello');
            $this->assertSame('hello', $this->c->make('greeting'));

            $clock = new Clock();
            $this->c->instance(Clock::class, $clock);
            $this->assertSame($clock, $this->c->make(Clock::class));
            $this->assertTrue($this->c->has(Clock::class));
        }

        public function testCircularDependencyIsReported(): void
        {
            $this->assertThrows(ContainerException::class, fn() => $this->c->make(Loop1::class));
        }

        public function testCallResolvesNamedPositionalInjectedAndDefaults(): void
        {
            $this->c->bind(Gateway::class, FakeGateway::class);
            $fn = fn(string $slug, Gateway $gateway, int $page = 1, ?string $q = null) => [$slug, $gateway->name(), $page, $q];

            $this->assertSame(['shoes', 'fake', 1, null], $this->c->call($fn, ['slug' => 'shoes']));
            $this->assertSame(['shoes', 'fake', 4, null], $this->c->call($fn, ['slug' => 'shoes', 'page' => '4']));
            $this->assertSame(['hats', 'fake', 2, null], $this->c->call($fn, ['first' => 'hats', 'second' => '2']), 'unnamed values fill in order');
            $this->assertThrows(ContainerException::class, fn() => $this->c->call(fn(string $required) => 1, []));
        }

        public function testRouteModelBinding(): void
        {
            $fn = fn(Product $product, Request $request) => [$product->name, get_class($request)];
            $this->assertSame(['Wireless Earbuds', Request::class], $this->c->call($fn, ['product' => '1']));
            $this->assertSame(['Wireless Earbuds', Request::class], $this->c->call($fn, ['id' => '1']), 'any route parameter name works by position');

            try {
                $this->c->call($fn, ['product' => '999999']);
                $this->assertTrue(false, 'expected 404');
            } catch (HttpException $e) {
                $this->assertSame(404, $e->status());
                $this->assertSame('Product not found', $e->getMessage());
            }

            $this->assertNull($this->c->call(fn(?Product $product = null) => $product, []), 'an optional model may be missing');
        }

        public function testGlobalHelper(): void
        {
            $this->assertTrue(app() instanceof Container);
            app()->singleton('test.value', fn() => new \ArrayObject([1]));
            $this->assertSame(app('test.value'), app('test.value'));
        }
    }
}
