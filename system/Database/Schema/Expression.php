<?php
namespace System\Database\Schema;

/**
 * A raw SQL fragment that must not be quoted, e.g. new Expression('CURRENT_TIMESTAMP')
 */
class Expression
{
    public function __construct(public string $sql) {}
}
