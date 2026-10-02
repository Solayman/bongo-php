<?php

namespace Tests\Unit\QueueProbe {
    use System\Queue\Job;

    class RecordJob extends Job
    {
        public static array $runs = [];
        public function __construct(public string $label) {}
        public function handle(): void { self::$runs[] = $this->label; }
    }

    class FlakyJob extends Job
    {
        public int $tries = 3;
        public int|array $backoff = 0;
        public static int $calls = 0;
        public static array $failedWith = [];
        public function __construct(public int $failTimes) {}
        public function handle(): void
        {
            self::$calls++;
            if (self::$calls <= $this->failTimes) throw new \RuntimeException("boom " . self::$calls);
        }
        public function failed(\Throwable $e): void { self::$failedWith[] = $e->getMessage(); }
    }
}

namespace Tests\Unit {

    use System\Database\DB;
    use System\Queue\Queue;
    use System\Queue\Worker;
    use System\Scheduling\CronExpression;
    use System\Scheduling\Schedule;
    use System\Testing\TestCase;
    use Tests\Unit\QueueProbe\FlakyJob;
    use Tests\Unit\QueueProbe\RecordJob;

    class QueueScheduleTest extends TestCase
    {
        protected string $driver;

        public function setUp(): void
        {
            $this->driver = (string) config('queue.driver');
            $GLOBALS['config']['queue']['driver'] = 'database';
            DB::table('jobs')->delete(true);
            DB::table('failed_jobs')->delete(true);
            RecordJob::$runs = [];
            FlakyJob::$calls = 0;
            FlakyJob::$failedWith = [];
        }

        public function tearDown(): void
        {
            $GLOBALS['config']['queue']['driver'] = $this->driver;
            DB::table('jobs')->delete(true);
            DB::table('failed_jobs')->delete(true);
        }

        public function testDatabaseQueueRunsJobsInOrder(): void
        {
            $id = dispatch(new RecordJob('first'));
            dispatch(new RecordJob('second'));
            $this->assertTrue($id > 0);
            $this->assertSame([], RecordJob::$runs, 'nothing runs until a worker picks it up');
            $this->assertSame(2, Queue::stats()['pending']);

            $this->assertSame(2, (new Worker())->work(['stop_when_empty' => true]));
            $this->assertSame(['first', 'second'], RecordJob::$runs);
            $this->assertSame(0, DB::table('jobs')->count(), 'finished jobs are removed');
        }

        public function testDelayedJobsWait(): void
        {
            Queue::later(3600, new RecordJob('later'));
            $this->assertSame(1, Queue::stats()['delayed']);
            (new Worker())->work(['stop_when_empty' => true]);
            $this->assertSame([], RecordJob::$runs);
        }

        public function testRetriesThenSucceeds(): void
        {
            dispatch(new FlakyJob(2));
            (new Worker())->work(['stop_when_empty' => true]);
            $this->assertSame(3, FlakyJob::$calls, 'failed twice, then succeeded on the third try');
            $this->assertSame(0, DB::table('failed_jobs')->count());
        }

        public function testFailsPermanentlyAndCanBeRetried(): void
        {
            dispatch(new FlakyJob(99));
            (new Worker())->work(['stop_when_empty' => true]);
            $this->assertSame(3, FlakyJob::$calls, '$tries = 3');
            $this->assertSame(['boom 3'], FlakyJob::$failedWith, 'failed() hook gets the last error');
            $this->assertSame(1, DB::table('failed_jobs')->count());
            $this->assertContains('RuntimeException: boom 3', DB::table('failed_jobs')->value('exception'));

            $this->assertSame(1, Worker::retry('all'));
            $this->assertSame(1, DB::table('jobs')->count());
            $this->assertSame(0, DB::table('failed_jobs')->count());
        }

        public function testCrashedJobsBecomeAvailableAgain(): void
        {
            $id = dispatch(new RecordJob('stuck'));
            DB::table('jobs')->where('id', $id)->update(['reserved_at' => time() - 3600, 'attempts' => 1]);
            (new Worker())->work(['stop_when_empty' => true]);
            $this->assertSame(['stuck'], RecordJob::$runs);
        }

        public function testSyncDriverRunsImmediately(): void
        {
            $GLOBALS['config']['queue']['driver'] = 'sync';
            $this->assertNull(dispatch(new RecordJob('now')));
            $this->assertSame(['now'], RecordJob::$runs);
        }

        public function testQueuedMailIsSentByTheWorker(): void
        {
            $dir = sys_get_temp_dir() . '/bongo_qmail_' . bin2hex(random_bytes(4));
            $old = $GLOBALS['config']['mail'];
            $GLOBALS['config']['mail'] = ['driver' => 'file', 'file_path' => $dir, 'from' => ['address' => 'shop@example.com']];
            \System\Mail\Mail::setMailer(null);
            try {
                \System\Mail\Mail::to('a@example.com')->subject('Queued hello')->text('hi')->queue();
                $this->assertSame([], glob("$dir/*.json") ?: [], 'not sent yet');
                (new Worker())->work(['stop_when_empty' => true]);
                $files = glob("$dir/*.json");
                $this->assertCount(1, $files);
                $this->assertSame('Queued hello', json_decode(file_get_contents($files[0]), true)['subject']);
            } finally {
                array_map('unlink', glob("$dir/*.json") ?: []);
                @rmdir($dir);
                $GLOBALS['config']['mail'] = $old;
                \System\Mail\Mail::setMailer(null);
            }
        }

        public function testCronExpressions(): void
        {
            $at = fn(string $t) => new \DateTimeImmutable($t);
            $this->assertTrue((new CronExpression('* * * * *'))->isDue($at('2026-10-02 13:37')));
            $this->assertTrue((new CronExpression('*/15 * * * *'))->isDue($at('2026-10-02 13:45')));
            $this->assertFalse((new CronExpression('*/15 * * * *'))->isDue($at('2026-10-02 13:46')));
            $this->assertTrue((new CronExpression('30 2 * * *'))->isDue($at('2026-10-02 02:30')));
            $this->assertTrue((new CronExpression('0 9 * * 1-5'))->isDue($at('2026-10-02 09:00')), 'Friday');
            $this->assertFalse((new CronExpression('0 9 * * 1-5'))->isDue($at('2026-10-03 09:00')), 'Saturday');
            $this->assertTrue((new CronExpression('0 0 * * 7'))->isDue($at('2026-10-04 00:00')), '7 = Sunday');
            $this->assertSame('2026-10-02 14:00', (new CronExpression('0 * * * *'))->nextRun($at('2026-10-02 13:37'))->format('Y-m-d H:i'));
            $this->assertThrows(\InvalidArgumentException::class, fn() => new CronExpression('* * *'));
            $this->assertThrows(\InvalidArgumentException::class, fn() => new CronExpression('61 * * * *'));
        }

        public function testScheduleFrequenciesAndDueTasks(): void
        {
            $schedule = new Schedule();
            $ran = [];
            $schedule->call(function () use (&$ran) { $ran[] = 'hourly'; })->hourly()->name('h');
            $schedule->call(function () use (&$ran) { $ran[] = 'report'; })->weekdays()->at('08:00')->name('r');
            $schedule->call(fn() => null)->dailyAt('02:30')->name('d');

            $this->assertSame(['h'], array_map(fn($t) => $t->getName(), $schedule->due(new \DateTimeImmutable('2026-10-02 13:00'))));
            $this->assertSame(['h', 'r'], array_map(fn($t) => $t->getName(), $schedule->due(new \DateTimeImmutable('2026-10-02 08:00'))), 'the hourly task is due at 08:00 too');
            $this->assertSame(['h'], array_map(fn($t) => $t->getName(), $schedule->due(new \DateTimeImmutable('2026-10-03 08:00'))), 'no weekday report on Saturday');
            $this->assertSame('30 2 * * *', $schedule->tasks()[2]->expression());

            foreach ($schedule->due(new \DateTimeImmutable('2026-10-02 13:00')) as $task) $task->run();
            $this->assertSame(['hourly'], $ran);
        }

        public function testWithoutOverlappingSkipsASecondRun(): void
        {
            $schedule = new Schedule();
            $inner = null;
            $task = $schedule->call(function () use (&$inner, &$task) { $inner = $task->run(); })->withoutOverlapping()->name('long');
            $this->assertSame('ran', $task->run());
            $this->assertSame('skipped', $inner, 'the nested run saw the lock');
        }

        public function testAppScheduleLoads(): void
        {
            $names = array_map(fn($t) => $t->getName(), Schedule::load()->tasks());
            $this->assertTrue(in_array('Email the low stock report', $names, true));
            $this->assertTrue(in_array('Prune expired tokens and login attempts', $names, true));
        }
    }
}
