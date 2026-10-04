<?php

namespace System\View;

/**
 * $loop inside {: each :}:
 *   $loop->index (0, 1, 2 ...)   $loop->iteration (1, 2, 3 ...)   $loop->first   $loop->last
 *   $loop->odd / even (by iteration)   $loop->count   $loop->remaining   $loop->depth   $loop->parent
 * count, remaining and last are null when the items cannot be counted (a generator).
 */
class Loop
{
    public int $index = -1;
    public int $iteration = 0;
    public bool $first = false;
    public ?bool $last = null;
    public bool $odd = false;
    public bool $even = false;
    public ?int $count;
    public ?int $remaining;
    public int $depth;

    public function __construct(mixed $items, public ?Loop $parent = null)
    {
        $this->count = is_countable($items) ? count($items) : null;
        $this->remaining = $this->count;
        $this->depth = $parent ? $parent->depth + 1 : 1;
    }

    /** Called at the start of each pass */
    public function next(): void
    {
        $this->index++;
        $this->iteration++;
        $this->first = $this->index === 0;
        $this->odd = $this->iteration % 2 === 1;
        $this->even = !$this->odd;
        if ($this->count !== null) {
            $this->remaining = $this->count - $this->iteration;
            $this->last = $this->remaining === 0;
        }
    }
}
