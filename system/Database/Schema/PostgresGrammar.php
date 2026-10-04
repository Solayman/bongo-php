<?php

namespace System\Database\Schema;

/**
 * The PostgreSQL side of Blueprint. Migrations compile to standard PostgreSQL DDL:
 *   - id() becomes BIGSERIAL PRIMARY KEY
 *   - boolean becomes BOOLEAN, tinyint becomes SMALLINT, json becomes JSONB
 *   - enum becomes VARCHAR(50) with a CHECK constraint
 *   - indexes are created via separate CREATE INDEX statements
 *   - comments are created via COMMENT ON COLUMN
 *   - identifiers are wrapped in double quotes
 */
trait PostgresGrammar
{
    protected function pgsqlCreate(): array
    {
        $lines = [];
        $primary = [];
        $inlinePrimary = false;

        foreach ($this->columns as $column) {
            $lines[] = $this->pgsqlColumn($column, true);
            if ($column->primary && $column->autoIncrement) {
                $inlinePrimary = true;
            } elseif ($column->primary) {
                $primary[] = self::wrapPgsql($column->name);
            }
        }
        if ($primary && !$inlinePrimary) {
            $lines[] = 'PRIMARY KEY (' . implode(', ', $primary) . ')';
        }
        foreach ($this->foreignDefinitionsPgsql() as $definition) {
            $lines[] = $definition;
        }

        return [
            'CREATE TABLE ' . self::wrapPgsql($this->table) . " (\n    " . implode(",\n    ", $lines) . "\n)",
            ...$this->pgsqlIndexes(),
            ...$this->pgsqlComments(),
            ...$this->pgsqlTriggers(),
        ];
    }

    protected function pgsqlAlter(): array
    {
        $table = 'ALTER TABLE ' . self::wrapPgsql($this->table);
        $statements = [];

        foreach ($this->drops as [$kind, $name]) {
            $statements[] = match ($kind) {
                'column'  => "$table DROP COLUMN " . self::wrapPgsql($name),
                'index'   => 'DROP INDEX IF EXISTS ' . self::wrapPgsql($name),
                'foreign' => "$table DROP CONSTRAINT IF EXISTS " . self::wrapPgsql($name),
            };
        }
        foreach ($this->renames as [$from, $to]) {
            $statements[] = "$table RENAME COLUMN " . self::wrapPgsql($from) . ' TO ' . self::wrapPgsql($to);
        }
        foreach ($this->columns as $column) {
            if ($column->primary) {
                throw new \LogicException("PostgreSQL cannot add the primary key column [{$column->name}] to an existing table directly. Use a separate migration constraint.");
            }
            $statements[] = "$table ADD COLUMN " . $this->pgsqlColumn($column, false);
        }

        return [...$statements, ...$this->pgsqlIndexes(), ...$this->pgsqlComments(), ...$this->pgsqlTriggers()];
    }

    protected function pgsqlColumn(ColumnDefinition $c, bool $creating): string
    {
        $name = self::wrapPgsql($c->name);

        if ($c->primary && $c->autoIncrement) {
            return "$name BIGSERIAL PRIMARY KEY";
        }

        $type = $c->type;
        $check = '';
        if (preg_match('/^ENUM\((.*)\)$/s', $type, $m)) {
            $type = 'VARCHAR(50)';
            $check = " CHECK ($name IN ({$m[1]}))";
        } elseif ($type === 'TINYINT(1)') {
            $type = 'BOOLEAN';
        } elseif ($type === 'TINYINT' || $type === 'SMALLINT') {
            $type = 'SMALLINT';
        } elseif ($type === 'INT' || $type === 'INTEGER') {
            $type = 'INTEGER';
        } elseif ($type === 'BIGINT') {
            $type = 'BIGINT';
        } elseif ($type === 'FLOAT') {
            $type = 'REAL';
        } elseif ($type === 'DOUBLE') {
            $type = 'DOUBLE PRECISION';
        } elseif (in_array($type, ['LONGTEXT', 'MEDIUMTEXT'], true)) {
            $type = 'TEXT';
        } elseif ($type === 'JSON') {
            $type = 'JSONB';
        } elseif (in_array($type, ['DATETIME', 'TIMESTAMP'], true)) {
            $type = 'TIMESTAMP';
        }

        $sql = "$name $type";
        $sql .= $c->nullable ? ' NULL' : ' NOT NULL';
        if ($c->hasDefault) {
            $sql .= ' DEFAULT ' . $this->pgsqlValue($c->default, $type);
        }
        $sql .= $check;

        if (!$creating && $c->foreign) {
            [$table, $references, $onDelete, $onUpdate] = $c->foreign;
            $sql .= ' REFERENCES ' . self::wrapPgsql($table) . ' (' . self::wrapPgsql($references) . ") ON DELETE $onDelete ON UPDATE $onUpdate";
        }

        return $sql;
    }

    protected function pgsqlIndexes(): array
    {
        $statements = [];
        $add = function (string $type, array $columns, ?string $name) use (&$statements) {
            $name ??= $this->table . '_' . implode('_', $columns) . ($type === 'UNIQUE' ? '_unique' : '_index');
            $statements[] = 'CREATE ' . ($type === 'UNIQUE' ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS ' . self::wrapPgsql($name)
                . ' ON ' . self::wrapPgsql($this->table) . ' (' . implode(', ', array_map([self::class, 'wrapPgsql'], $columns)) . ')';
        };

        foreach ($this->columns as $column) {
            if ($column->unique) {
                $add('UNIQUE', [$column->name], null);
            }
            if ($column->index || $column->foreign) {
                $add('INDEX', [$column->name], null);
            }
        }
        foreach ($this->indexes as [$type, $columns, $name]) {
            $add($type, $columns, $name);
        }

        return $statements;
    }

    protected function pgsqlComments(): array
    {
        $statements = [];
        foreach ($this->columns as $column) {
            if ($column->comment !== null) {
                $table = self::wrapPgsql($this->table);
                $col = self::wrapPgsql($column->name);
                $comment = self::quote($column->comment);
                $statements[] = "COMMENT ON COLUMN $table.$col IS $comment";
            }
        }
        return $statements;
    }

    /** ->useCurrentOnUpdate(): trigger to refresh timestamp in PostgreSQL */
    protected function pgsqlTriggers(): array
    {
        $statements = [];
        foreach ($this->columns as $column) {
            if ($column->onUpdate === null) {
                continue;
            }
            $table = self::wrapPgsql($this->table);
            $rawTable = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
            $col = self::wrapPgsql($column->name);
            $rawCol = preg_replace('/[^a-zA-Z0-9_]/', '', $column->name);
            $funcName = self::wrapPgsql("bongo_{$rawTable}_{$rawCol}_trigger");
            $trigName = self::wrapPgsql("trg_{$rawTable}_{$rawCol}_update");

            $statements[] = "CREATE OR REPLACE FUNCTION $funcName() RETURNS TRIGGER AS \$\$\nBEGIN\n    NEW.$col = CURRENT_TIMESTAMP;\n    RETURN NEW;\nEND;\n\$\$ LANGUAGE plpgsql";
            $statements[] = "DROP TRIGGER IF EXISTS $trigName ON $table";
            $statements[] = "CREATE TRIGGER $trigName BEFORE UPDATE ON $table FOR EACH ROW EXECUTE FUNCTION $funcName()";
        }
        return $statements;
    }

    protected function foreignDefinitionsPgsql(): array
    {
        $definitions = [];
        foreach ($this->columns as $column) {
            if (!$column->foreign) {
                continue;
            }
            [$table, $references, $onDelete, $onUpdate] = $column->foreign;
            $fkName = "{$this->table}_{$column->name}_foreign";
            $definitions[] = 'CONSTRAINT ' . self::wrapPgsql($fkName)
                . ' FOREIGN KEY (' . self::wrapPgsql($column->name) . ')'
                . ' REFERENCES ' . self::wrapPgsql($table) . ' (' . self::wrapPgsql($references) . ")"
                . " ON DELETE $onDelete ON UPDATE $onUpdate";
        }
        return $definitions;
    }

    protected function pgsqlValue(mixed $value, string $type = ''): string
    {
        if ($value instanceof Expression) {
            return $value->sql;
        }
        if (strtoupper($type) === 'BOOLEAN' || is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return self::value($value);
    }

    public static function wrapPgsql(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
