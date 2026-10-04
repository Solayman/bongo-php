<?php
namespace System\Database;

/**
 * Parent has one related row: related.foreignKey = parent.localKey ($user->profile)
 */
class HasOne extends HasOneOrMany
{
    protected bool $one = true;

    public function getResults(): ?Model
    {
        return $this->query->first();
    }
}
