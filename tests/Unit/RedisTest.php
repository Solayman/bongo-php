<?php

namespace Tests\Unit;

use System\Cache\RedisStore;
use System\Http\RedisSessionHandler;
use System\Testing\TestCase;

/**
 * Runs when the redis extension is loaded and a Redis server answers
 * (REDIS_HOST / REDIS_PORT); otherwise every test passes without checks.
 */
class RedisTest extends TestCase
{
    protected ?\Redis $redis = null;
    protected string $prefix = 'bongo:testing:unit:';

    public function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('redis')) {
            return;
        }
        try {
            $redis = new \Redis();
            $redis->connect((string) config('database.redis.host'), (int) config('database.redis.port'), 0.5);
            $redis->ping();
            $this->redis = $redis;
        } catch (\Throwable) {
            $this->redis = null;
        }
    }

    protected function store(): ?RedisStore
    {
        if ($this->redis === null) {
            $this->assertTrue(true);
            return null;
        }
        $store = new RedisStore($this->redis, $this->prefix . 'cache:');
        $store->flush();
        return $store;
    }

    public function testPutGetForgetAndRemember(): void
    {
        if (!$store = $this->store()) {
            return;
        }

        $this->assertNull($store->get('missing'));
        $this->assertSame('x', $store->get('missing', 'x'));

        $store->put('user', ['id' => 7, 'name' => 'Ana'], 60);
        $this->assertSame(['id' => 7, 'name' => 'Ana'], $store->get('user'));
        $this->assertTrue($store->has('user'));
        $this->assertTrue($store->ttl('user') > 50);

        $store->put('forever', false);
        $this->assertFalse($store->get('forever', 'default'), 'false is a value, not a miss');
        $this->assertNull($store->ttl('forever'));

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return 42;
        };
        $this->assertSame(42, $store->remember('slow', 60, $compute));
        $this->assertSame(42, $store->remember('slow', 60, $compute));
        $this->assertSame(1, $calls);

        $store->forget('user');
        $this->assertFalse($store->has('user'));
        $this->assertSame(0, $store->ttl('user'));
    }

    public function testIncrementIsAtomicAndKeepsExpiry(): void
    {
        if (!$store = $this->store()) {
            return;
        }

        $this->assertSame(1, $store->increment('hits', 1, 120));
        $this->assertSame(6, $store->increment('hits', 5, 9999));
        $this->assertSame(6, $store->get('hits'));
        $this->assertTrue($store->ttl('hits') <= 120, 'the first expiry is kept');
        $this->assertSame(4, $store->increment('hits', -2));
    }

    public function testFlushOnlyRemovesThisAppsKeys(): void
    {
        if (!$store = $this->store()) {
            return;
        }

        $this->redis->set($this->prefix . 'other-app', '1');
        $store->put('a', 1);
        $store->put('b', 2);

        $this->assertSame(2, $store->flush());
        $this->assertFalse($store->has('a'));
        $this->assertSame('1', $this->redis->get($this->prefix . 'other-app'));
        $this->redis->del($this->prefix . 'other-app');
    }

    public function testSessionHandler(): void
    {
        if ($this->redis === null) {
            $this->assertTrue(true);
            return;
        }
        $handler = new RedisSessionHandler(60, $this->redis, $this->prefix . 'session:');
        $id = bin2hex(random_bytes(16));

        $this->assertSame('', $handler->read($id));
        $this->assertFalse($handler->validateId($id), 'unknown ids are rejected (strict mode)');

        $handler->write($id, 'auth_user|i:5;');
        $this->assertSame('auth_user|i:5;', $handler->read($id));
        $this->assertTrue($handler->validateId($id));
        $this->assertTrue($this->redis->ttl($this->prefix . 'session:' . $id) <= 60);

        $handler->destroy($id);
        $this->assertSame('', $handler->read($id));
    }

    public function testRedisQueueDriver(): void
    {
        if ($this->redis === null) {
            $this->assertTrue(true);
            return;
        }

        $prevDriver = config('queue.driver');
        $GLOBALS['config']['queue']['driver'] = 'redis';

        try {
            $prefix = \System\Redis\Redis::prefix();
            $queueKey = $prefix . 'queue:default';
            $this->redis->del($queueKey);
            $this->redis->del($queueKey . ':delayed');

            RedisJobProbe::$ran = false;
            $id = \System\Queue\Queue::push(new RedisJobProbe());
            $this->assertNotNull($id);

            $stats = \System\Queue\Queue::stats('default');
            $this->assertSame(1, $stats['pending']);

            $worker = new \System\Queue\Worker();
            $processed = $worker->work(['once' => true, 'queue' => 'default']);

            $this->assertSame(1, $processed);
            $this->assertTrue(RedisJobProbe::$ran);

            $statsAfter = \System\Queue\Queue::stats('default');
            $this->assertSame(0, $statsAfter['pending']);
            $this->assertSame(0, $statsAfter['reserved'], 'a finished job is no longer reserved');
        } finally {
            $GLOBALS['config']['queue']['driver'] = $prevDriver;
        }
    }

    public function testRedisJobSurvivesAWorkerCrash(): void
    {
        if ($this->redis === null) {
            $this->assertTrue(true);
            return;
        }
        $prev = [config('queue.driver'), config('queue.retry_after')];
        $GLOBALS['config']['queue']['driver'] = 'redis';
        $queueKey = \System\Redis\Redis::prefix() . 'queue:crash';
        $this->redis->del($queueKey, "$queueKey:delayed", "$queueKey:reserved");

        try {
            \System\Queue\Queue::push(new RedisJobProbe(), 0, 'crash');

            // A worker takes the job and "dies" before finishing it
            $GLOBALS['config']['queue']['retry_after'] = 0;
            $this->assertNotNull((new \System\Queue\Worker())->reserve('crash'));
            $this->assertSame(1, \System\Queue\Queue::stats('crash')['reserved']);

            // The next worker gets it back, because its reservation expired
            sleep(1);
            RedisJobProbe::$ran = false;
            $GLOBALS['config']['queue']['retry_after'] = 90;
            $this->assertSame(1, (new \System\Queue\Worker())->work(['once' => true, 'queue' => 'crash']));
            $this->assertTrue(RedisJobProbe::$ran, 'the job was not lost');
            $this->assertSame(0, \System\Queue\Queue::stats('crash')['reserved']);
        } finally {
            [$GLOBALS['config']['queue']['driver'], $GLOBALS['config']['queue']['retry_after']] = $prev;
            $this->redis->del($queueKey, "$queueKey:delayed", "$queueKey:reserved");
        }
    }

    public function testUnsignedRedisJobsAreNeverUnserialized(): void
    {
        if ($this->redis === null) {
            $this->assertTrue(true);
            return;
        }
        $prev = config('queue.driver');
        $GLOBALS['config']['queue']['driver'] = 'redis';
        $queueKey = \System\Redis\Redis::prefix() . 'queue:forged';
        $this->redis->del($queueKey, "$queueKey:reserved");

        try {
            // Someone with access to Redis writes a job without the APP_KEY signature
            RedisJobProbe::$ran = false;
            $forged = json_encode(['id' => 'x', 'queue' => 'forged', 'job' => base64_encode(serialize(new RedisJobProbe())), 'attempts' => 0]);
            $this->redis->rPush($queueKey, $forged);
            $this->redis->rPush($queueKey, json_encode(['body' => $forged, 'sig' => str_repeat('0', 64)]));

            $this->assertNull((new \System\Queue\Worker())->reserve('forged'));
            $this->assertNull((new \System\Queue\Worker())->reserve('forged'));
            $this->assertFalse(RedisJobProbe::$ran);
            $this->assertSame(0, (int) $this->redis->zCard("$queueKey:reserved"));
        } finally {
            $GLOBALS['config']['queue']['driver'] = $prev;
            $this->redis->del($queueKey, "$queueKey:reserved");
        }
    }
}

class RedisJobProbe extends \System\Queue\Job
{
    public static bool $ran = false;
    public function handle(): void
    {
        self::$ran = true;
    }
}
