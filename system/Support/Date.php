<?php

namespace System\Support;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Stringable;

class Date extends DateTime implements JsonSerializable, Stringable
{
    public static function appTimezone(): DateTimeZone
    {
        $tz = (string) config('app.timezone', 'UTC');
        try {
            return new DateTimeZone($tz ?: 'UTC');
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    public static function now(?string $timezone = null): static
    {
        $tz = $timezone !== null ? new DateTimeZone($timezone) : static::appTimezone();
        return new static('now', $tz);
    }

    public static function today(?string $timezone = null): static
    {
        return static::now($timezone)->startOfDay();
    }

    public static function parse(string|DateTimeInterface|int|null $time = 'now', ?string $timezone = null): static
    {
        $tz = $timezone !== null ? new DateTimeZone($timezone) : static::appTimezone();

        if ($time === null || $time === '') {
            $time = 'now';
        }

        if (is_numeric($time)) {
            $date = new static('@' . $time);
            return $date->setTimezone($tz);
        }

        if ($time instanceof DateTimeInterface) {
            $date = new static($time->format('Y-m-d H:i:s.u'), $time->getTimezone());
            return $date->setTimezone($tz);
        }

        $date = new static($time, $tz);
        return $date->setTimezone($tz);
    }

    public static function createFromFormat(string $format, string $datetime, ?DateTimeZone $timezone = null): static|false
    {
        $tz = $timezone ?? static::appTimezone();
        $dt = parent::createFromFormat($format, $datetime, $tz);
        if ($dt === false) {
            return false;
        }
        return new static($dt->format('Y-m-d H:i:s.u'), $dt->getTimezone());
    }

    public function timezone(string|DateTimeZone $timezone): static
    {
        $tz = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        return $this->setTimezone($tz);
    }

    public function addDays(int $days = 1): static
    {
        $clone = clone $this;
        $clone->modify(($days >= 0 ? "+$days" : "$days") . ' days');
        return $clone;
    }

    public function subDays(int $days = 1): static
    {
        return $this->addDays(-$days);
    }

    public function addHours(int $hours = 1): static
    {
        $clone = clone $this;
        $clone->modify(($hours >= 0 ? "+$hours" : "$hours") . ' hours');
        return $clone;
    }

    public function subHours(int $hours = 1): static
    {
        return $this->addHours(-$hours);
    }

    public function addMinutes(int $minutes = 1): static
    {
        $clone = clone $this;
        $clone->modify(($minutes >= 0 ? "+$minutes" : "$minutes") . ' minutes');
        return $clone;
    }

    public function subMinutes(int $minutes = 1): static
    {
        return $this->addMinutes(-$minutes);
    }

    public function addSeconds(int $seconds = 1): static
    {
        $clone = clone $this;
        $clone->modify(($seconds >= 0 ? "+$seconds" : "$seconds") . ' seconds');
        return $clone;
    }

    public function subSeconds(int $seconds = 1): static
    {
        return $this->addSeconds(-$seconds);
    }

    public function startOfDay(): static
    {
        $clone = clone $this;
        $clone->setTime(0, 0, 0);
        return $clone;
    }

    public function endOfDay(): static
    {
        $clone = clone $this;
        $clone->setTime(23, 59, 59);
        return $clone;
    }

    public function toIsoString(): string
    {
        return $this->format('Y-m-d\TH:i:sP');
    }

    public function toDateTimeString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function toDateString(): string
    {
        return $this->format('Y-m-d');
    }

    public function toTimeString(): string
    {
        return $this->format('H:i:s');
    }

    public function diffForHumans(?DateTimeInterface $other = null): string
    {
        $target = $other ? self::parse($other) : self::now($this->getTimezone()->getName());
        $diff = $target->getTimestamp() - $this->getTimestamp();
        $isFuture = $diff < 0;
        $abs = abs($diff);

        if ($abs < 5) {
            return 'just now';
        }

        $units = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
            1        => 'second',
        ];

        foreach ($units as $secs => $name) {
            if ($abs >= $secs) {
                $count = (int) floor($abs / $secs);
                $label = $count === 1 ? $name : $name . 's';
                return $isFuture ? "in $count $label" : "$count $label ago";
            }
        }

        return 'just now';
    }

    public function jsonSerialize(): string
    {
        return $this->toIsoString();
    }

    public function __toString(): string
    {
        return $this->toDateTimeString();
    }
}
