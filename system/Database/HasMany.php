<?php

namespace System\Database;

/**
 * Parent has many related rows: related.foreignKey = parent.localKey
 */
class HasMany extends HasOneOrMany
{
    public function getResults(): array
    {
        return $this->query->get();
    }
}
