<?php
namespace System\Scheduling;

use System\Queue\Job;

/**
 * Task list for `php bongo schedule:run` (run it from cron every minute):
 *   * * * * * cd /path/to/app && php bongo schedule:run >> /dev/null 2>&1
 *
 * Define tasks in app/schedule.php:
 *   $schedule->call(fn() => ...)->hourly()->name('Refresh stats');
 *   $schedule->command('cache:clear')->dailyAt('03:00');
 *   $schedule->job(new SendDailyReport())->weekdays()->at('08:00');
 */
class Schedule
{
    /** @var Task[] */
    protected array $tasks = [];

    public function call(callable $callback): Task
    {
        return $this->tasks[] = new Task($callback);
    }

    /** A bongo command, e.g. command('view:clear') */
    public function command(string $command, array $arguments = []): Task
    {
        $task = $this->tasks[] = new Task(function () use ($command, $arguments) {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/bongo') . ' ' . escapeshellarg($command);
            foreach ($arguments as $arg) $cmd .= ' ' . escapeshellarg((string) $arg);
            exec($cmd . ' 2>&1', $output, $code);
            if ($code !== 0) {
                throw new \RuntimeException("bongo $command exited with $code: " . implode("\n", array_slice($output, -5)));
            }
        });
        return $task->name("bongo $command");
    }

    /** Put a job on the queue when due */
    public function job(Job $job): Task
    {
        return $this->call(fn() => dispatch($job))->name('Job: ' . $job->displayName());
    }

    /** @return Task[] */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /** @return Task[] tasks due at $at */
    public function due(\DateTimeInterface $at): array
    {
        return array_values(array_filter($this->tasks, fn(Task $t) => $t->isDue($at)));
    }

    /** Build the schedule from app/schedule.php (and the framework's own tasks) */
    public static function load(): self
    {
        $schedule = new self();
        Maintenance::register($schedule);
        $file = BASE_PATH . '/app/schedule.php';
        if (is_file($file)) {
            (static function (Schedule $schedule) use ($file) {
                require $file;
            })($schedule);
        }
        return $schedule;
    }
}
