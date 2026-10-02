<?php
namespace System\Scheduling;

use System\Cache\Cache;
use System\Log\Log;

/**
 * One scheduled task. Frequency helpers set a cron expression:
 *   ->everyMinute() ->everyFiveMinutes() ->everyFifteenMinutes() ->hourly() ->hourlyAt(15)
 *   ->daily() ->dailyAt('02:30') ->weekdays() ->weekly() ->monthly() ->cron('0 9 * * 1-5')
 */
class Task
{
    protected string $cron = '* * * * *';
    protected ?string $name = null;
    protected bool $withoutOverlapping = false;
    protected int $lockMinutes = 60;

    /** @var callable */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? 'Closure task';
    }

    public function cron(string $expression): static
    {
        new CronExpression($expression); // validate now
        $this->cron = $expression;
        return $this;
    }

    public function expression(): string
    {
        return $this->cron;
    }

    public function everyMinute(): static          { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static     { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static      { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static  { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static   { return $this->cron('0,30 * * * *'); }
    public function hourly(): static               { return $this->cron('0 * * * *'); }
    public function hourlyAt(int $minute): static  { return $this->cron("$minute * * * *"); }
    public function daily(): static                { return $this->cron('0 0 * * *'); }
    public function weekly(): static               { return $this->cron('0 0 * * 0'); }
    public function monthly(): static              { return $this->cron('0 0 1 * *'); }

    public function dailyAt(string $time): static
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => 0]);
        return $this->cron("$m $h * * *");
    }

    /** Keep the time, limit to Monday–Friday: ->weekdays()->at('08:00') */
    public function weekdays(): static
    {
        $parts = explode(' ', $this->cron);
        $parts[4] = '1-5';
        return $this->cron(implode(' ', $parts));
    }

    /** Set the time of day on the current expression */
    public function at(string $time): static
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => 0]);
        $parts = explode(' ', $this->cron);
        [$parts[0], $parts[1]] = [(string) $m, (string) $h];
        return $this->cron(implode(' ', $parts));
    }

    /** Skip a run while the previous one is still going (lock in the cache) */
    public function withoutOverlapping(int $expiresAfterMinutes = 60): static
    {
        $this->withoutOverlapping = true;
        $this->lockMinutes = $expiresAfterMinutes;
        return $this;
    }

    public function isDue(\DateTimeInterface $at): bool
    {
        return (new CronExpression($this->cron))->isDue($at);
    }

    public function nextRun(\DateTimeInterface $from): ?\DateTimeImmutable
    {
        return (new CronExpression($this->cron))->nextRun($from);
    }

    /** Run now; returns 'ran', 'skipped' (overlap) or throws */
    public function run(): string
    {
        $lock = 'schedule-lock:' . sha1($this->getName() . $this->cron);
        if ($this->withoutOverlapping) {
            if (Cache::increment($lock, 1, $this->lockMinutes * 60) > 1) {
                Log::info('Scheduled task "{task}" skipped: still running', ['task' => $this->getName()]);
                return 'skipped';
            }
        }
        try {
            \System\Container\Container::getInstance()->call($this->callback);
            Log::info('Scheduled task "{task}" ran', ['task' => $this->getName()]);
            return 'ran';
        } finally {
            if ($this->withoutOverlapping) {
                Cache::forget($lock);
            }
        }
    }
}
