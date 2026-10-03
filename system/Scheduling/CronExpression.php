<?php
namespace System\Scheduling;

/**
 * Standard 5-field cron expressions: minute hour day-of-month month day-of-week
 *   "* * * * *"  every minute      "* /5 * * * *" (no space) every 5 minutes
 *   "30 2 * * *" 02:30 daily       "0 9 * * 1-5" 09:00 on weekdays
 * Supports *, numbers, ranges (1-5), lists (1,3,5) and steps (* /15, 0-30/10).
 * Day-of-week: 0 or 7 = Sunday.
 */
class CronExpression
{
    protected array $fields;

    public function __construct(protected string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Cron expression must have 5 fields: [$expression]");
        }
        $ranges = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];
        foreach ($parts as $i => $part) {
            $this->fields[$i] = $this->parse($part, ...$ranges[$i]);
        }
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function isDue(\DateTimeInterface $at): bool
    {
        [$minute, $hour, $day, $month, $weekday] = $this->fields;
        $dow = (int) $at->format('w');

        return in_array((int) $at->format('i'), $minute, true)
            && in_array((int) $at->format('G'), $hour, true)
            && in_array((int) $at->format('n'), $month, true)
            && $this->dayMatches((int) $at->format('j'), $dow, $day, $weekday);
    }

    /** The next time (after $from) this expression is due, searched minute by minute up to a year ahead */
    public function nextRun(\DateTimeInterface $from): ?\DateTimeImmutable
    {
        $t = \DateTimeImmutable::createFromInterface($from)->setTime((int) $from->format('G'), (int) $from->format('i'))->modify('+1 minute');
        for ($i = 0; $i < 527040; $i++) {
            if ($this->isDue($t)) return $t;
            $t = $t->modify('+1 minute');
        }
        return null;
    }

    /** Standard cron: if both day-of-month and day-of-week are restricted, either may match */
    protected function dayMatches(int $dom, int $dow, array $days, array $weekdays): bool
    {
        $domRestricted = count($days) < 31;
        $dowRestricted = count(array_unique(array_map(fn($d) => $d % 7, $weekdays))) < 7;
        $domOk = in_array($dom, $days, true);
        $dowOk = in_array($dow, $weekdays, true) || ($dow === 0 && in_array(7, $weekdays, true));

        if ($domRestricted && $dowRestricted) return $domOk || $dowOk;
        return $domOk && $dowOk;
    }

    protected function parse(string $field, int $min, int $max): array
    {
        $values = [];
        foreach (explode(',', $field) as $item) {
            if (!preg_match('/^(\*|\d+(?:-\d+)?)(?:\/(\d+))?$/', $item, $m)) {
                throw new \InvalidArgumentException("Invalid cron field [$field] in [{$this->expression}]");
            }
            $step = isset($m[2]) ? max(1, (int) $m[2]) : 1;
            if ($m[1] === '*') {
                [$from, $to] = [$min, $max];
            } elseif (str_contains($m[1], '-')) {
                [$from, $to] = array_map('intval', explode('-', $m[1]));
            } else {
                $from = $to = (int) $m[1];
                if (isset($m[2])) $to = $max; // "5/15" = from 5 every 15
            }
            if ($from < $min || $to > $max || $from > $to) {
                throw new \InvalidArgumentException("Cron value out of range in [{$this->expression}]");
            }
            for ($v = $from; $v <= $to; $v += $step) $values[] = $v;
        }
        return array_values(array_unique($values));
    }
}
