<?php
namespace System\Database\Schema;

/**
 * The SQLite side of Blueprint. Migrations stay the same for both databases;
 * only the generated SQL differs:
 *   - id() becomes INTEGER PRIMARY KEY AUTOINCREMENT
 *   - indexes are separate CREATE INDEX statements
 *   - enum() becomes TEXT with a CHECK, json/dates become TEXT
 *   - ->useCurrentOnUpdate() becomes a trigger (SQLite has no ON UPDATE for columns)
 *   - CURRENT_TIMESTAMP uses PHP's timezone, like the MySQL connection does
 */
trait SqliteGrammar
{
    protected function sqliteCreate(): array
    {
        $lines = [];
        $primary = [];
        $inlinePrimary = false;

        foreach ($this->columns as $column) {
            $lines[] = $this->sqliteColumn($column, true);
            if ($column->primary && $column->autoIncrement) {
                $inlinePrimary = true;
            } elseif ($column->primary) {
                $primary[] = $this->wrapName($column->name);
            }
        }
        if ($primary && !$inlinePrimary) {
            $lines[] = 'PRIMARY KEY (' . implode(', ', $primary) . ')';
        }
        foreach ($this->foreignDefinitions() as $definition) {
            $lines[] = $definition;
        }

        return [
            'CREATE TABLE ' . $this->wrapName($this->table) . " (\n    " . implode(",\n    ", $lines) . "\n)",
            ...$this->sqliteIndexes(),
            ...$this->sqliteTriggers(),
        ];
    }

    protected function sqliteAlter(): array
    {
        $table = 'ALTER TABLE ' . $this->wrapName($this->table);
        $statements = [];

        foreach ($this->drops as [$kind, $name]) {
            $statements[] = match ($kind) {
                'column'  => "$table DROP COLUMN " . $this->wrapName($name),
                'index'   => 'DROP INDEX ' . $this->wrapName($name),
                'foreign' => throw new \LogicException(
                    "SQLite cannot drop the foreign key [$name] of an existing table. Create a new table without it and copy the rows over."
                ),
            };
        }
        foreach ($this->renames as [$from, $to]) {
            $statements[] = "$table RENAME COLUMN " . $this->wrapName($from) . ' TO ' . $this->wrapName($to);
        }
        foreach ($this->columns as $column) {
            if ($column->primary) {
                throw new \LogicException("SQLite cannot add the primary key column [{$column->name}] to an existing table.");
            }
            // ->after() has no meaning in SQLite: new columns always go last
            $statements[] = "$table ADD COLUMN " . $this->sqliteColumn($column, false);
        }

        return [...$statements, ...$this->sqliteIndexes(), ...$this->sqliteTriggers()];
    }

    protected function sqliteColumn(ColumnDefinition $c, bool $creating): string
    {
        $name = $this->wrapName($c->name);

        if ($c->primary && $c->autoIncrement) {
            return "$name INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        $type = $c->type;
        $check = '';
        if (preg_match('/^ENUM\((.*)\)$/s', $type, $m)) {
            $type = 'TEXT';
            $check = " CHECK ($name IN ({$m[1]}))";
        } elseif (in_array($type, ['JSON', 'DATE', 'DATETIME', 'TIMESTAMP'], true)) {
            // TEXT affinity keeps the values exactly as written
            $type = 'TEXT';
        }

        $sql = "$name $type";
        $sql .= $c->nullable ? ' NULL' : ' NOT NULL';
        if ($c->hasDefault) {
            $sql .= ' DEFAULT ' . $this->sqliteValue($c->default);
        }
        $sql .= $check;

        // Adding a column: the foreign key goes on the column itself
        if (!$creating && $c->foreign) {
            [$table, $references, $onDelete, $onUpdate] = $c->foreign;
            $sql .= ' REFERENCES ' . $this->wrapName($table) . ' (' . $this->wrapName($references) . ") ON DELETE $onDelete ON UPDATE $onUpdate";
        }

        return $sql;
    }

    protected function sqliteIndexes(): array
    {
        $statements = [];
        $add = function (string $type, array $columns, ?string $name) use (&$statements) {
            $name ??= $this->table . '_' . implode('_', $columns) . ($type === 'UNIQUE' ? '_unique' : '_index');
            $statements[] = 'CREATE ' . ($type === 'UNIQUE' ? 'UNIQUE ' : '') . 'INDEX ' . $this->wrapName($name)
                . ' ON ' . $this->wrapName($this->table) . ' (' . implode(', ', array_map([$this, 'wrapName'], $columns)) . ')';
        };

        foreach ($this->columns as $column) {
            if ($column->unique) $add('UNIQUE', [$column->name], null);
            if ($column->index || $column->foreign) $add('INDEX', [$column->name], null);
        }
        foreach ($this->indexes as [$type, $columns, $name]) {
            $add($type, $columns, $name);
        }

        return $statements;
    }

    /** ->useCurrentOnUpdate(): refresh the column whenever the row changes and the column was not set */
    protected function sqliteTriggers(): array
    {
        $statements = [];
        foreach ($this->columns as $column) {
            if ($column->onUpdate === null) continue;
            $table = $this->wrapName($this->table);
            $col = $this->wrapName($column->name);
            $statements[] = 'CREATE TRIGGER IF NOT EXISTS ' . $this->wrapName("{$this->table}_{$column->name}_on_update")
                . " AFTER UPDATE ON $table FOR EACH ROW WHEN NEW.$col IS OLD.$col"
                . " BEGIN UPDATE $table SET $col = " . self::sqliteNow() . ' WHERE rowid = NEW.rowid; END';
        }
        return $statements;
    }

    protected function sqliteValue(mixed $value): string
    {
        if ($value instanceof Expression && strtoupper($value->sql) === 'CURRENT_TIMESTAMP') {
            return '(' . self::sqliteNow() . ')';
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return self::value($value);
    }

    /** The current time in PHP's timezone (SQLite's CURRENT_TIMESTAMP is always UTC) */
    public static function sqliteNow(): string
    {
        $minutes = intdiv((int) date('Z'), 60);
        return "datetime('now', '" . ($minutes >= 0 ? '+' : '') . "$minutes minutes')";
    }
}
