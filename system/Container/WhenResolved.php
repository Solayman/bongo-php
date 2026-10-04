<?php

namespace System\Container;

/**
 * Objects that finish setting themselves up once the container has built them
 * (a FormRequest validates the input here).
 */
interface WhenResolved
{
    public function resolved(): void;
}
